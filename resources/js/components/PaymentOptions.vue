<template>
  <div class="flex flex-col items-center justify-center h-full">
    <h2 class="text-4xl font-bold text-white mb-8">Оплати и сохрани</h2>
    <div v-if="loading" class="mt-8 text-center">
      <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-white mx-auto"></div>
      <p class="text-white text-xl mt-4">Создание платежа...</p>
    </div>
    <div v-else-if="qrCodeData" class="mt-8 p-6 bg-white rounded-lg shadow-xl">
      <img :src="`data:image/png;base64,${qrCodeData}`" alt="QR Code" class="w-64 h-64" />
      <p class="text-center mt-4 text-lg font-medium">Отсканируйте QR-код для оплаты</p>
      <p class="text-center mt-2 text-sm text-gray-500">После оплаты вы автоматически вернетесь на сайт</p>
      <div v-if="checkingPayment" class="mt-4 text-center">
        <p class="text-gray-600">Проверка статуса оплаты...</p>
        <div class="animate-pulse mt-2">●●●</div>
      </div>
    </div>
    <div v-else-if="error" class="mt-8 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
      <p class="text-center">{{ error }}</p>
      <button
        @click="initPayment()"
        class="mt-4 bg-red-600 text-white text-lg font-bold py-2 px-4 rounded hover:bg-red-700 transition-colors"
      >
        Попробовать снова
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { apiService } from '../services/api';
import { useOrderStore } from '../stores/order';

const orderStore = useOrderStore();
const qrCodeData = ref('');
const loading = ref(false);
const error = ref('');
const checkingPayment = ref(false);
let paymentCheckInterval: number | null = null;

// Инициализация платежа с помощью QR-кода Альфа-банка
const initPayment = async () => {
  if (!orderStore.orderId) {
    error.value = 'Ошибка: ID заказа не найден';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    // Используем метод alfapay для оплаты через Альфа-банк с QR-кодом
    const response = await apiService.initPayment(orderStore.orderId, 'alfapay');

    // Получаем QR-код в формате Base64 из ответа API
    if (response.data && response.data.qr_code) {
      qrCodeData.value = response.data.qr_code;
      emit('payment-initiated', 'alfapay');

      // Начинаем проверку статуса оплаты
      startPaymentCheck();
    } else {
      throw new Error('QR-код не получен от сервера');
    }
  } catch (err) {
    console.error('Ошибка при инициализации платежа:', err);
    error.value = 'Не удалось создать платеж. Пожалуйста, попробуйте снова.';
  } finally {
    loading.value = false;
  }
};

// Начинаем проверку статуса оплаты
const startPaymentCheck = async () => {
  if (paymentCheckInterval) {
    clearInterval(paymentCheckInterval);
  }

  checkingPayment.value = true;

  // Сразу проверяем статус, чтобы не ждать первый интервал
  try {
    const initialResponse = await apiService.getOrderStatus(orderStore.orderId);
    const initialOrderStatus = initialResponse.data.order?.status || initialResponse.data.status;

    console.log('Initial order status check:', initialOrderStatus); // Отладочное сообщение

    // Если заказ уже оплачен, сразу останавливаем проверку и уведомляем родительский компонент
    if (initialOrderStatus === 'paid') {
      console.log('Payment already confirmed, emitting payment-success event'); // Отладочное сообщение
      stopPaymentCheck();
      checkingPayment.value = false;

      // Обновляем статус заказа в хранилище
      orderStore.updateStatus('paid');

      // Уведомляем родительский компонент об успешной оплате
      emit('payment-success');
      return; // Выходим, чтобы не запускать интервал
    }
  } catch (err) {
    console.error('Ошибка при начальной проверке статуса платежа:', err);
  }

  // Проверяем статус каждые 3 секунды
  paymentCheckInterval = window.setInterval(async () => {
    try {
      console.log('Making API call to check order status...'); // Дополнительное отладочное сообщение
      const response = await apiService.getOrderStatus(orderStore.orderId);
      console.log('Full API response:', response); // Полный ответ для отладки
      const orderStatus = response.data.order?.status;

      console.log('Checking order status:', orderStatus); // Отладочное сообщение

      // Если заказ оплачен, останавливаем проверку и уведомляем родительский компонент
      if (orderStatus === 'paid') {
        console.log('Payment confirmed, emitting payment-success event'); // Отладочное сообщение
        stopPaymentCheck();
        checkingPayment.value = false;

        // Обновляем статус заказа в хранилище
        orderStore.updateStatus('paid');

        // Уведомляем родительский компонент об успешной оплате
        emit('payment-success');
      } else {
        console.log('Order status is not paid yet, continuing checks...'); // Отладочное сообщение
      }
    } catch (err) {
      console.error('Ошибка при проверке статуса платежа:', err);
    }
  }, 3000); // Проверяем каждые 3 секунды
};

// Останавливаем проверку статуса оплаты
const stopPaymentCheck = () => {
  if (paymentCheckInterval) {
    clearInterval(paymentCheckInterval);
    paymentCheckInterval = null;
  }
  checkingPayment.value = false;
};

// Автоматически инициализируем платеж при монтировании компонента
onMounted(() => {
  initPayment();
});

// Очищаем интервал при размонтировании компонента
onUnmounted(() => {
  stopPaymentCheck();
});

const emit = defineEmits(['payment-initiated', 'payment-success']);
</script>
