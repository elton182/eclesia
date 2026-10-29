import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { registerSW } from 'virtual:pwa-register'

import App from './App.vue'
import router from './router'
import i18n from './i18n'
import { registerInstallPrompt } from '@/utils/pwa'
import { watchServiceWorkerUpdates } from '@/utils/pwaUpdate'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { library } from '@fortawesome/fontawesome-svg-core';
import { faEdit, faTrash, faBuilding     } from '@fortawesome/free-solid-svg-icons';

library.add(faEdit, faTrash, faBuilding);

registerSW({
  immediate: true,
  onRegisteredSW(_swUrl, registration) {
    watchServiceWorkerUpdates(registration)
  },
})
registerInstallPrompt()

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(i18n)
app.component('font-awesome-icon', FontAwesomeIcon)
app.mount('#app')
