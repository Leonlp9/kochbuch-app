import { ref, watch } from 'vue'
import type { AIRecipeResult } from '@/services/writeApi'

export interface ChatMessage {
  id: string
  role: 'user' | 'model'
  /** Plain text for user; HTML for model */
  content: string
  recipeLinks?: { id: number; name: string }[]
  hasDraft?: boolean
  recipeDraft?: AIRecipeResult
  timestamp: number
}

export interface ChatSession {
  id: string
  name: string
  createdAt: number
  messages: ChatMessage[]
}

const STORAGE_KEY = 'kochbuch_chats'

function load(): ChatSession[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as ChatSession[]) : []
  } catch {
    return []
  }
}

export const sessions = ref<ChatSession[]>(load())
export const activeChatId = ref<string | null>(sessions.value[0]?.id ?? null)
export const isChatOpen = ref(false)

watch(sessions, (v) => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(v))
  } catch { /* quota exceeded */ }
}, { deep: true })

export function createChat(): ChatSession {
  const chat: ChatSession = {
    id: crypto.randomUUID(),
    name: 'Neuer Chat',
    createdAt: Date.now(),
    messages: [],
  }
  sessions.value.unshift(chat)
  activeChatId.value = chat.id
  return chat
}

export function deleteChat(id: string) {
  sessions.value = sessions.value.filter((c) => c.id !== id)
  if (activeChatId.value === id) {
    activeChatId.value = sessions.value[0]?.id ?? null
  }
}

export function setActiveChat(id: string) {
  activeChatId.value = id
}

export function getActiveChat(): ChatSession | undefined {
  return sessions.value.find((c) => c.id === activeChatId.value)
}

export function ensureActiveChat(): ChatSession {
  const existing = getActiveChat()
  if (existing) return existing
  return createChat()
}

export function pushMessage(chatId: string, msg: Omit<ChatMessage, 'id'>): ChatMessage {
  const chat = sessions.value.find((c) => c.id === chatId)
  if (!chat) throw new Error('Chat not found')
  const m: ChatMessage = { ...msg, id: crypto.randomUUID() }
  chat.messages.push(m)
  // Name aus erster User-Nachricht ableiten
  if (chat.name === 'Neuer Chat' && msg.role === 'user') {
    chat.name = msg.content.slice(0, 50) + (msg.content.length > 50 ? '\u2026' : '')
  }
  return m
}

