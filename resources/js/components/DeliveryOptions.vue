<template>
  <div class="flex flex-col items-center justify-center h-full">
    <h2 class="text-4xl font-bold text-white mb-8">Как получить фото?</h2>
    <div class="flex flex-col space-y-6 w-full max-w-md">
      <button
        v-if="!showQrCode"
        @click="sendDelivery('telegram')"
        class="bg-blue-600 text-white text-3xl font-bold py-4 px-8 rounded-full shadow-lg hover:bg-blue-700 transition-colors"
      >
        Telegram
      </button>
      <div v-else class="flex flex-col items-center">
        <h3 class="text-2xl font-bold text-white mb-4">Получите это фото в Telegram</h3>
        <div class="bg-white p-4 rounded-lg" v-html="qrCodeSvg"></div>
        <p class="text-white mt-4">Отсканируйте QR-код или введите код вручную</p>
        <div class="mt-2 text-white text-xl font-bold">Код: {{ orderStore.orderCode }}</div>
        <p class="text-white mt-2">В Telegram боте введите: /start {{ orderStore.orderCode }}</p>
        <a
          :href="telegramDeepLink"
          target="_blank"
          class="bg-blue-600 text-white text-xl font-bold py-2 px-4 rounded-full shadow-lg hover:bg-blue-700 transition-colors mt-4"
        >
          Открыть в Telegram
        </a>
        <button
          @click="showQrCode = false"
          class="mt-4 text-white underline"
        >
          Назад
        </button>
      </div>
      <div class="flex">
        <input
          v-model="email"
          type="email"
          placeholder="Email"
          class="flex-grow p-4 text-2xl rounded-l-full border-0"
        />
        <button
          @click="sendDelivery('email')"
          class="bg-green-600 text-white text-2xl font-bold py-4 px-6 rounded-r-full shadow-lg hover:bg-green-700 transition-colors"
        >
          Email
        </button>
      </div>
      <button
        @click="sendDelivery('print')"
        class="bg-purple-600 text-white text-3xl font-bold py-4 px-8 rounded-full shadow-lg hover:bg-purple-700 transition-colors"
      >
        Напечатать
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { apiService } from '../services/api';
import { useOrderStore } from '../stores/order';

const orderStore = useOrderStore();
const email = ref('');
const showQrCode = ref(false);
const qrCodeSvg = ref('');
const telegramDeepLink = ref('');

const sendDelivery = async (channel: 'telegram' | 'email' | 'print') => {
  if (!orderStore.orderId) {
    console.error('No order ID available');
    return;
  }

  try {
    if (channel === 'email' && !email.value) {
      alert('Пожалуйста, введите email');
      return;
    }

    if (channel === 'email') {
      await apiService.sendDeliveryEmail(orderStore.orderId, email.value);
    } else if (channel === 'print') {
      await apiService.sendDeliveryPrint(orderStore.orderId);
    } else if (channel === 'telegram') {
      // Generate QR code for Telegram
      const response = await apiService.generateTelegramQr(orderStore.orderId);
      // The response is SVG content, so we can use it directly
      qrCodeSvg.value = await response.data;

      // Get the order details to retrieve the code
      const orderResponse = await apiService.getOrderStatus(orderStore.orderId);
      if (orderResponse.data.order && orderResponse.data.order.code) {
        orderStore.setOrderCode(orderResponse.data.order.code);

        // Generate the deep link URL using the code
        const telegramBotUsername = import.meta.env.VITE_TELEGRAM_BOT_USERNAME || 'your_bot_username';
        telegramDeepLink.value = `https://t.me/${telegramBotUsername}?start=${orderResponse.data.order.code}`;
      }

      showQrCode.value = true;
      return; // Don't emit event yet, user will handle Telegram separately
    }

    // Emit event to parent
    emit('delivery-sent', channel);
  } catch (error) {
    console.error(`Failed to send delivery via ${channel}:`, error);
  }
};

const emit = defineEmits(['delivery-sent']);
</script>
