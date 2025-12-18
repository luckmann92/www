<template>
  <div class="flex flex-col items-center justify-center h-full">
    <h2 class="text-4xl font-bold text-white mb-8">Оплати и сохрани</h2>
    <div class="flex flex-col space-y-6">
      <button
        @click="initPayment('sbp')"
        class="bg-green-600 text-white text-3xl font-bold py-4 px-8 rounded-full shadow-lg hover:bg-green-700 transition-colors"
      >
        СБП
      </button>
      <button
        @click="initPayment('mir')"
        class="bg-blue-600 text-white text-3xl font-bold py-4 px-8 rounded-full shadow-lg hover:bg-blue-700 transition-colors"
      >
        МИР
      </button>
    </div>
    <div v-if="paymentUrl" class="mt-8 p-4 bg-white rounded-lg">
      <img :src="`data:image/png;base64,${qrCodeData}`" alt="QR Code" class="w-64 h-64" />
      <p class="text-center mt-4 text-lg">Отсканируй QR для оплаты</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { apiService } from '../services/api';
import { useOrderStore } from '../stores/order';

const orderStore = useOrderStore();
const paymentUrl = ref<string | null>(null);
const qrCodeData = ref('');

const initPayment = async (method: 'sbp' | 'mir') => {
 if (!orderStore.orderId) {
    console.error('No order ID available');
    return;
  }

  try {
    const response = await apiService.initPayment(orderStore.orderId, method);
    paymentUrl.value = response.data.payment_url;

    // In a real app, you would generate the QR code from paymentUrl
    // For now, we'll use a placeholder
    qrCodeData.value = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='; // 1x1 pixel

    // Emit event to parent to handle payment status
    emit('payment-initiated', method);
  } catch (error) {
    console.error('Failed to init payment:', error);
  }
};

const emit = defineEmits(['payment-initiated']);
</script>
