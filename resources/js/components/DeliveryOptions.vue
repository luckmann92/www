<template>
  <div class="flex flex-col items-center justify-center h-full">
    <h2 class="text-4xl font-bold text-white mb-8">Как получить фото?</h2>
    <div class="flex flex-col space-y-6 w-full max-w-md">
      <button
        @click="sendDelivery('telegram')"
        class="bg-blue-600 text-white text-3xl font-bold py-4 px-8 rounded-full shadow-lg hover:bg-blue-700 transition-colors"
      >
        Telegram
      </button>
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
    }
    // Telegram is handled differently, usually by showing a QR with a deep-link

    // Emit event to parent
    emit('delivery-sent', channel);
  } catch (error) {
    console.error(`Failed to send delivery via ${channel}:`, error);
  }
};

const emit = defineEmits(['delivery-sent']);
</script>
