import { createApp } from 'vue';
import { createPinia } from 'pinia';
import Kiosk from './pages/Kiosk.vue';

const app = createApp(Kiosk);
const pinia = createPinia();

app.use(pinia);
app.mount('#app');
