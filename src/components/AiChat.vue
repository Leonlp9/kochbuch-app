<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  sessions,
  activeChatId,
  isChatOpen,
  createChat,
  deleteChat,
  setActiveChat,
  getActiveChat,
  ensureActiveChat,
  pushMessage,
} from '@/stores/chat'
import {
  sendChatMessage,
  type ChatHistoryItem,
  type AIRecipeResult,
} from '@/services/writeApi'
import { isOnline } from '@/services/network'

const route  = useRoute()
const router = useRouter()

const inputText   = ref('')
const sending     = ref(false)
const showSidebar = ref(false)
const messagesEl  = ref<HTMLElement | null>(null)

// Aktuelles Rezept aus der Route ableiten
const currentRecipeId = computed(() => {
  if (route.name === 'recipe') return route.params.id as string
  return null
})

const activeChat = computed(() => getActiveChat())

// Chat-Verlauf für API (nur Text, letzten 14 Nachrichten)
function buildHistory(): ChatHistoryItem[] {
  const chat = activeChat.value
  if (!chat) return []
  return chat.messages.slice(-14).map((m) => ({
    role: m.role,
    content: m.role === 'user' ? m.content : stripHtml(m.content).slice(0, 400),
  }))
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
}

async function sendMessage() {
  const text = inputText.value.trim()
  if (!text || sending.value) return
  if (!isOnline.value) return

  const chat = ensureActiveChat()
  inputText.value = ''
  sending.value = true

  pushMessage(chat.id, { role: 'user', content: text, timestamp: Date.now() })
  scrollToBottom()

  try {
    const history = buildHistory().slice(0, -1) // ohne aktuelle Msg
    const res = await sendChatMessage(text, history, currentRecipeId.value)

    if (!res.success || !res.reply) throw new Error(res.error ?? 'Fehler')

    const r = res.reply
    pushMessage(chat.id, {
      role: 'model',
      content: r.message,
      recipeLinks: r.recipe_links ?? [],
      hasDraft: r.has_draft ?? false,
      recipeDraft: r.has_draft ? r.recipe_draft : undefined,
      timestamp: Date.now(),
    })
  } catch (e) {
    pushMessage(chat.id, {
      role: 'model',
      content: `<p><strong>Fehler:</strong> ${e instanceof Error ? e.message : 'Unbekannter Fehler'}</p>`,
      recipeLinks: [],
      timestamp: Date.now(),
    })
  } finally {
    sending.value = false
    scrollToBottom()
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesEl.value) {
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight
    }
  })
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    sendMessage()
  }
}

function openChat() {
  isChatOpen.value = true
  ensureActiveChat()
  nextTick(scrollToBottom)
}

function closeChat() {
  isChatOpen.value = false
  showSidebar.value = false
}

function toggleChat() {
  if (isChatOpen.value) closeChat()
  else openChat()
}

function switchChat(id: string) {
  setActiveChat(id)
  showSidebar.value = false
  nextTick(scrollToBottom)
}

function newChat() {
  createChat()
  showSidebar.value = false
}

function removeChat(id: string, e: MouseEvent) {
  e.stopPropagation()
  deleteChat(id)
  if (sessions.value.length === 0) createChat()
}

function goToRecipe(id: number) {
  router.push(`/recipe/${id}`).catch(() => {})
  closeChat()
}

function useDraft(draft: AIRecipeResult) {
  try {
    localStorage.setItem('kochbuch_ai_draft', JSON.stringify(draft))
  } catch { /* ignore */ }
  router.push('/new').catch(() => {})
  closeChat()
}

function formatDate(ts: number): string {
  return new Date(ts).toLocaleDateString('de-DE', {
    day: '2-digit', month: '2-digit', year: '2-digit',
    hour: '2-digit', minute: '2-digit',
  })
}

// Scroll on new messages when chat is open
watch(() => activeChat.value?.messages.length, () => {
  if (isChatOpen.value) scrollToBottom()
})

onMounted(() => {
  if (sessions.value.length === 0) createChat()
})
</script>

<template>
  <Teleport to="body">
    <!-- Floating Action Button -->
    <button
      class="ai-fab"
      :class="{ 'ai-fab--open': isChatOpen }"
      title="KI-Assistent"
      @click="toggleChat"
    >
      <Transition name="fab-icon" mode="out-in">
        <i v-if="!isChatOpen" key="chat" class="fa-solid fa-comment-dots" />
        <i v-else key="close" class="fa-solid fa-xmark" />
      </Transition>
      <span class="ai-fab-ripple" />
    </button>

    <!-- Chat Panel -->
    <Transition name="chat-panel">
      <div v-if="isChatOpen" class="chat-wrap">
        <!-- Backdrop (mobile) -->
        <div class="chat-backdrop" @click="closeChat" />

        <div class="chat-panel">
          <!-- Sidebar -->
          <Transition name="sidebar-slide">
            <aside v-if="showSidebar" class="chat-sidebar">
              <div class="sidebar-header">
                <span class="sidebar-title">Chats</span>
                <button class="icon-btn" @click="newChat" title="Neuer Chat">
                  <i class="fa-solid fa-plus" />
                </button>
              </div>
              <div class="sidebar-list">
                <button
                  v-for="s in sessions"
                  :key="s.id"
                  class="sidebar-item"
                  :class="{ active: s.id === activeChatId }"
                  @click="switchChat(s.id)"
                >
                  <div class="sidebar-item-content">
                    <span class="sidebar-item-name">{{ s.name }}</span>
                    <span class="sidebar-item-date">{{ formatDate(s.createdAt) }}</span>
                  </div>
                  <button
                    class="icon-btn danger sidebar-del"
                    title="Chat l&ouml;schen"
                    @click="removeChat(s.id, $event)"
                  >
                    <i class="fa-solid fa-trash" />
                  </button>
                </button>
                <div v-if="sessions.length === 0" class="sidebar-empty">
                  Keine Chats
                </div>
              </div>
            </aside>
          </Transition>

          <!-- Main Chat Area -->
          <div class="chat-main">
            <!-- Header -->
            <header class="chat-header">
              <button class="icon-btn" title="Chats" @click="showSidebar = !showSidebar">
                <i class="fa-solid fa-bars" />
              </button>
              <div class="chat-header-info">
                <i class="fa-solid fa-wand-magic-sparkles chat-ai-icon" />
                <span class="chat-header-title">KI-Assistent</span>
                <span v-if="currentRecipeId" class="chat-context-badge">
                  <i class="fa-solid fa-book-open" /> Rezept-Kontext aktiv
                </span>
              </div>
              <div class="chat-header-actions">
                <button class="icon-btn" title="Neuer Chat" @click="newChat">
                  <i class="fa-solid fa-plus" />
                </button>
                <button class="icon-btn" title="Schlie&szlig;en" @click="closeChat">
                  <i class="fa-solid fa-xmark" />
                </button>
              </div>
            </header>

            <!-- Messages -->
            <div ref="messagesEl" class="chat-messages">
              <!-- Empty state -->
              <div v-if="!activeChat || activeChat.messages.length === 0" class="chat-empty">
                <i class="fa-solid fa-wand-magic-sparkles chat-empty-icon" />
                <p><strong>Hallo! Ich bin dein KI-Kochassistent.</strong></p>
                <p>
                  Ich kenne alle Rezepte in deiner App und helfe dir beim Kochen.<br />
                  Du kannst mich fragen:
                </p>
                <ul class="chat-suggestions">
                  <li @click="inputText = 'Was kann ich heute kochen?'">
                    <i class="fa-solid fa-utensils" /> Was kann ich heute kochen?
                  </li>
                  <li @click="inputText = 'Erstell mir ein Rezept f&uuml;r Tiramisu'">
                    <i class="fa-solid fa-plus" /> Erstell mir ein Rezept f&uuml;r Tiramisu
                  </li>
                  <li @click="inputText = 'Welche Rezepte gibt es unter 30 Minuten?'">
                    <i class="fa-solid fa-clock" /> Rezepte unter 30 Minuten
                  </li>
                  <li v-if="currentRecipeId" @click="inputText = 'Was passt dazu als Beilage?'">
                    <i class="fa-solid fa-question" /> Was passt dazu als Beilage?
                  </li>
                </ul>
              </div>

              <!-- Message bubbles -->
              <template v-if="activeChat">
                <div
                  v-for="msg in activeChat.messages"
                  :key="msg.id"
                  class="msg-row"
                  :class="msg.role === 'user' ? 'msg-row--user' : 'msg-row--model'"
                >
                  <div class="msg-bubble">
                    <!-- User message: plain text -->
                    <template v-if="msg.role === 'user'">
                      {{ msg.content }}
                    </template>

                    <!-- Model message: HTML content -->
                    <template v-else>
                      <!-- eslint-disable-next-line vue/no-v-html -->
                      <div class="msg-html" v-html="msg.content" />

                      <!-- Recipe links -->
                      <div v-if="msg.recipeLinks && msg.recipeLinks.length > 0" class="msg-links">
                        <button
                          v-for="link in msg.recipeLinks"
                          :key="link.id"
                          class="recipe-chip"
                          @click="goToRecipe(link.id)"
                        >
                          <i class="fa-solid fa-book-open" />
                          {{ link.name }}
                        </button>
                      </div>

                      <!-- Recipe draft button -->
                      <div v-if="msg.hasDraft && msg.recipeDraft" class="msg-draft">
                        <button class="draft-btn" @click="useDraft(msg.recipeDraft!)">
                          <i class="fa-solid fa-pen-to-square" />
                          Rezept &bdquo;{{ msg.recipeDraft.recipe_name }}&ldquo; anlegen
                        </button>
                      </div>
                    </template>
                  </div>
                  <span class="msg-time">{{ formatDate(msg.timestamp) }}</span>
                </div>
              </template>

              <!-- Typing indicator -->
              <div v-if="sending" class="msg-row msg-row--model">
                <div class="msg-bubble msg-bubble--typing">
                  <span /><span /><span />
                </div>
              </div>
            </div>

            <!-- Offline hint -->
            <div v-if="!isOnline" class="chat-offline">
              <i class="fa-solid fa-plug-circle-xmark" /> Keine Serververbindung
            </div>

            <!-- Input -->
            <div class="chat-input-wrap">
              <textarea
                v-model="inputText"
                class="chat-input"
                placeholder="Nachricht eingeben..."
                rows="1"
                :disabled="sending || !isOnline"
                @keydown="onKeydown"
              />
              <button
                class="chat-send"
                :disabled="!inputText.trim() || sending || !isOnline"
                @click="sendMessage"
              >
                <i v-if="sending" class="fa-solid fa-spinner fa-spin" />
                <i v-else class="fa-solid fa-paper-plane" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* ── FAB ── */
.ai-fab {
  position: fixed;
  bottom: calc(var(--nav-h-mobile, 64px) + 20px + env(safe-area-inset-bottom, 0px));
  right: 20px;
  z-index: 500;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--accent);
  color: var(--on-accent, #fff);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  box-shadow: 0 4px 16px rgba(0,0,0,.25);
  transition: transform 0.25s cubic-bezier(.34,1.56,.64,1), background 0.2s;
  overflow: hidden;
}
@media (min-width: 769px) {
  .ai-fab {
    bottom: 28px;
    right: 28px;
  }
}
.ai-fab:hover { transform: scale(1.08); }
.ai-fab--open { transform: scale(1.06) rotate(90deg); }
.ai-fab--open:hover { transform: scale(1.12) rotate(90deg); }

.ai-fab-ripple {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: rgba(255,255,255,0.15);
  transform: scale(0);
  transition: transform 0.4s;
}
.ai-fab:active .ai-fab-ripple { transform: scale(2); transition: none; }

/* Icon swap animation */
.fab-icon-enter-active,
.fab-icon-leave-active { transition: opacity 0.15s, transform 0.15s; }
.fab-icon-enter-from  { opacity: 0; transform: rotate(-90deg) scale(0.6); }
.fab-icon-leave-to    { opacity: 0; transform: rotate(90deg)  scale(0.6); }

/* ── Chat panel wrapper ── */
.chat-wrap {
  position: fixed;
  inset: 0;
  z-index: 490;
  pointer-events: none;
  display: flex;
  align-items: flex-end;
  justify-content: flex-end;
  padding: 0 20px calc(var(--nav-h-mobile, 64px) + 86px + env(safe-area-inset-bottom, 0px)) 0;
}
@media (min-width: 769px) {
  .chat-wrap {
    padding: 0 98px 100px 0;
  }
}

.chat-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,.35);
  pointer-events: all;
}
@media (min-width: 769px) {
  .chat-backdrop { display: none; }
}

/* Panel transition */
.chat-panel-enter-active,
.chat-panel-leave-active {
  transition: opacity 0.25s ease, transform 0.28s cubic-bezier(.34,1.36,.64,1);
}
.chat-panel-enter-from,
.chat-panel-leave-to {
  opacity: 0;
  transform: translateY(40px) scale(0.92);
}

/* ── Chat panel ── */
.chat-panel {
  position: relative;
  pointer-events: all;
  display: flex;
  width: min(420px, calc(100vw - 40px));
  height: min(600px, calc(100dvh - var(--nav-h-mobile, 64px) - 110px));
  border-radius: var(--r-lg, 16px);
  overflow: hidden;
  box-shadow: 0 8px 40px rgba(0,0,0,.28);
  background: var(--surface);
  border: 1px solid var(--line);
}
@media (max-width: 480px) {
  /* FAB mit X verstecken – Chat hat eigenen Schließen-Button */
  .ai-fab--open {
    display: none;
  }
  /*
   * Fullscreen-Chat: Safe-Areas direkt im inset einrechnen.
   * Der Wrapper wird zwischen Status-/Navigationsleiste positioniert,
   * sodass kein Inhalt dahinter verschwindet – unabhängig davon, ob
   * env(safe-area-inset-*) korrekt befüllt ist.
   */
  .chat-wrap {
    top: env(safe-area-inset-top, 0px);
    right: 0;
    bottom: env(safe-area-inset-bottom, 0px);
    left: 0;
    padding: 0;
    align-items: stretch;
    justify-content: stretch;
  }
  .chat-panel {
    width: 100%;
    height: 100%;
    border-radius: 0;
    border: none;
  }
  .chat-backdrop { display: none; }
}

/* ── Sidebar ── */
.chat-sidebar {
  position: absolute;
  top: 0; left: 0; bottom: 0;
  width: 240px;
  background: var(--surface-2, #f5f3f0);
  border-right: 1px solid var(--line);
  z-index: 10;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.sidebar-slide-enter-active,
.sidebar-slide-leave-active { transition: transform 0.22s ease, opacity 0.22s; }
.sidebar-slide-enter-from,
.sidebar-slide-leave-to    { transform: translateX(-100%); opacity: 0; }

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-bottom: 1px solid var(--line);
  flex-shrink: 0;
}
.sidebar-title { font-weight: 700; font-size: 0.9rem; color: var(--ink); }
.sidebar-list { flex: 1; overflow-y: auto; padding: 6px; display: flex; flex-direction: column; gap: 2px; }
.sidebar-empty { padding: 12px; font-size: 0.8rem; color: var(--ink-faint); text-align: center; }
.sidebar-item {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 10px;
  border-radius: var(--r-md, 8px);
  border: none;
  background: transparent;
  width: 100%;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}
.sidebar-item:hover { background: var(--accent-soft); }
.sidebar-item.active { background: var(--accent-soft); }
.sidebar-item-content { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.sidebar-item-name { font-size: 0.8rem; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar-item-date { font-size: 0.7rem; color: var(--ink-faint); }
.sidebar-del { opacity: 0; transition: opacity 0.15s; flex-shrink: 0; }
.sidebar-item:hover .sidebar-del { opacity: 1; }

/* ── Main ── */
.chat-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: var(--surface);
}

/* Header */
.chat-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--line);
  flex-shrink: 0;
  background: var(--surface);
}
.chat-header-info { flex: 1; display: flex; align-items: center; gap: 7px; min-width: 0; }
.chat-ai-icon { color: var(--accent); font-size: 0.95rem; flex-shrink: 0; }
.chat-header-title { font-weight: 700; font-size: 0.9rem; white-space: nowrap; }
.chat-context-badge {
  display: inline-flex; align-items: center; gap: 4px;
  background: var(--accent-soft); color: var(--accent-strong);
  font-size: 0.7rem; font-weight: 600; padding: 2px 8px;
  border-radius: 99px; white-space: nowrap;
}
.chat-header-actions { display: flex; gap: 2px; flex-shrink: 0; }

/* Messages */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 14px 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  scroll-behavior: smooth;
}
.chat-empty {
  margin: auto;
  text-align: center;
  color: var(--ink-soft);
  font-size: 0.85rem;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}
.chat-empty-icon { font-size: 2.2rem; color: var(--accent); opacity: 0.7; margin-bottom: 4px; }
.chat-empty p { margin: 0; }
.chat-suggestions {
  list-style: none;
  padding: 0;
  margin: 4px 0 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
}
.chat-suggestions li {
  padding: 8px 12px;
  background: var(--surface-2);
  border-radius: var(--r-md, 8px);
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 7px;
  transition: background 0.15s;
}
.chat-suggestions li:hover { background: var(--accent-soft); color: var(--accent-strong); }

/* Message rows */
.msg-row {
  display: flex;
  flex-direction: column;
  gap: 3px;
  max-width: 88%;
}
.msg-row--user { align-self: flex-end; align-items: flex-end; }
.msg-row--model { align-self: flex-start; align-items: flex-start; }

.msg-bubble {
  padding: 10px 13px;
  border-radius: 16px;
  font-size: 0.85rem;
  line-height: 1.5;
  word-break: break-word;
}
.msg-row--user .msg-bubble {
  background: var(--accent);
  color: var(--on-accent, #fff);
  border-bottom-right-radius: 4px;
}
.msg-row--model .msg-bubble {
  background: var(--surface-2);
  color: var(--ink);
  border-bottom-left-radius: 4px;
  border: 1px solid var(--line);
}

/* HTML content in AI messages */
.msg-html :deep(p)  { margin: 0 0 6px; }
.msg-html :deep(p:last-child) { margin-bottom: 0; }
.msg-html :deep(ul), .msg-html :deep(ol)  { margin: 4px 0; padding-left: 18px; }
.msg-html :deep(li)  { margin: 2px 0; }
.msg-html :deep(strong) { font-weight: 700; }

/* Recipe link chips */
.msg-links {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid var(--line);
}
.recipe-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  background: var(--accent-soft);
  color: var(--accent-strong);
  border: 1.5px solid transparent;
  border-radius: 99px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.recipe-chip:hover { border-color: var(--accent); }

/* Draft button */
.msg-draft { margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--line); }
.draft-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 14px;
  background: var(--accent);
  color: var(--on-accent, #fff);
  border: none;
  border-radius: var(--r-md, 8px);
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  width: 100%;
  justify-content: center;
  transition: opacity 0.15s;
}
.draft-btn:hover { opacity: 0.88; }

/* Typing indicator */
.msg-bubble--typing {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 12px 16px;
  min-width: 52px;
}
.msg-bubble--typing span {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: var(--accent, #888);
  animation: typing-dot 1.2s infinite both;
}
.msg-bubble--typing span:nth-child(2) { animation-delay: 0.2s; }
.msg-bubble--typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing-dot {
  0%, 80%, 100% { opacity: 0.3; transform: translateY(0); }
  40%           { opacity: 1;   transform: translateY(-5px); }
}

.msg-time {
  font-size: 0.68rem;
  color: var(--ink-faint);
  padding: 0 4px;
}

/* Offline banner */
.chat-offline {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 7px 14px;
  background: var(--danger-soft);
  color: var(--danger);
  font-size: 0.78rem;
  font-weight: 600;
  flex-shrink: 0;
}

/* Input */
.chat-input-wrap {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding: 10px 12px;
  border-top: 1px solid var(--line);
  background: var(--surface);
  flex-shrink: 0;
}
.chat-input {
  flex: 1;
  min-height: 40px;
  max-height: 120px;
  resize: none;
  border: 1.5px solid var(--line);
  border-radius: var(--r-md, 8px);
  background: var(--surface-2);
  color: var(--ink);
  padding: 8px 12px;
  font-size: 0.875rem;
  font-family: inherit;
  outline: none;
  line-height: 1.45;
  overflow-y: auto;
  field-sizing: content;
}
.chat-input:focus { border-color: var(--accent); }
.chat-input:disabled { opacity: 0.5; }

.chat-send {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: var(--r-md, 8px);
  background: var(--accent);
  color: var(--on-accent, #fff);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  transition: opacity 0.15s, transform 0.15s;
}
.chat-send:hover:not(:disabled) { transform: scale(1.06); }
.chat-send:disabled { opacity: 0.4; cursor: not-allowed; }

/* Icon buttons reused from app */
.icon-btn {
  width: 34px; height: 34px;
  border: none; border-radius: var(--r-sm, 6px);
  background: transparent; color: var(--ink-soft);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; flex-shrink: 0;
}
.icon-btn:hover { background: var(--surface-2); color: var(--ink); }
.icon-btn.danger:hover { background: var(--danger-soft); color: var(--danger); }
</style>



