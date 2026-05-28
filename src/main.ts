import { createApp } from 'vue'
import { createPinia } from 'pinia'

// Schriften (gebuendelt -> offline verfuegbar)
import '@fontsource/fraunces/400.css'
import '@fontsource/fraunces/600.css'
import '@fontsource/fraunces/700.css'
import '@fontsource/fraunces/900.css'
import '@fontsource/work-sans/400.css'
import '@fontsource/work-sans/500.css'
import '@fontsource/work-sans/600.css'
import '@fontsource/work-sans/700.css'

// Styles
import '@fortawesome/fontawesome-free/css/all.min.css'
import './styles/tokens.css'
import './styles/base.css'

import App from './App.vue'
import router from './router'
import { useUiStore } from './stores/ui'
import { initNetwork } from './services/network'

const app = createApp(App)
app.use(createPinia())
app.use(router)

// Theme moeglichst frueh setzen (vermeidet Flackern)
const ui = useUiStore()
ui.initTheme()

void initNetwork()

app.mount('#app')
