<template>
  <div class="bg-gray-900 text-white h-screen w-screen flex flex-col">
    <!-- Main content area -->
    <div class="flex-grow flex items-center justify-center p-8">
      <HeroSlider v-if="uiStore.currentScreen === 'home'" @start="startSession" />
      <CameraCapture v-else-if="uiStore.currentScreen === 'camera'" @photo-captured="onPhotoCaptured" />
      <PreviewPhoto v-else-if="uiStore.currentScreen === 'preview'" :photo="currentPhoto" @retake="retakePhoto" @confirm="confirmPhoto" />
      <CollageSelect v-else-if="uiStore.currentScreen === 'collage'" @collage-selected="onCollageSelected" />
      <ProgressScreen v-else-if="uiStore.currentScreen === 'processing'" />
      <BlurredResult v-else-if="uiStore.currentScreen === 'blurred'" :imageUrl="orderStore.blurredImageUrl || ''" @payment-requested="initiatePayment" />
      <PaymentOptions v-else-if="uiStore.currentScreen === 'payment'" @payment-initiated="onPaymentInitiated" @payment-success="onPaymentSuccess" />
      <DeliveryOptions v-else-if="uiStore.currentScreen === 'delivery'" @delivery-sent="onDeliverySent" />
      <ThankYou v-else-if="uiStore.currentScreen === 'thank-you'" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { apiService } from '../services/api';
import { useSessionStore } from '../stores/session';
import { useOrderStore } from '../stores/order';
import { useUiStore } from '../stores/ui';
import HeroSlider from '../components/HeroSlider.vue';
import CameraCapture from '../components/CameraCapture.vue';
import PreviewPhoto from '../components/PreviewPhoto.vue';
import CollageSelect from '../components/CollageSelect.vue';
import ProgressScreen from '../components/ProgressScreen.vue';
import BlurredResult from '../components/BlurredResult.vue';
import PaymentOptions from '../components/PaymentOptions.vue';
import DeliveryOptions from '../components/DeliveryOptions.vue';
import ThankYou from '../components/ThankYou.vue';

const sessionStore = useSessionStore();
const orderStore = useOrderStore();
const uiStore = useUiStore();

const currentPhoto = ref('');

const startSession = async () => {
  try {
    const response = await apiService.startSession(1); // Assuming device ID is 1 for now
    sessionStore.setSession(response.data.session_token, response.data.session_token); // session_token is used as ID in our API
    uiStore.setCurrentScreen('camera');
  } catch (error) {
    console.error('Failed to start session:', error);
  }
};

const onPhotoCaptured = async (photo: string) => {
  currentPhoto.value = photo;
  uiStore.setCurrentScreen('preview');

  // Если фото не было захвачено (пустая строка), сразу перейти к выбору коллажа
  if (!photo) {
    // Имитируем быстрый переход к выбору коллажа, если камера недоступна
    setTimeout(() => {
      uiStore.setCurrentScreen('collage');
    }, 3000);
  }
};

const retakePhoto = () => {
  uiStore.setCurrentScreen('camera');
};

const confirmPhoto = async () => {
  if (!sessionStore.sessionToken) {
    console.error('No session token available');
    return;
  }

  // Если фото не было захвачено, пропускаем загрузку и переходим к выбору коллажа
  if (!currentPhoto.value) {
    uiStore.setCurrentScreen('collage');
    return;
  }

  try {
    await apiService.uploadPhoto(sessionStore.sessionToken, currentPhoto.value);
    uiStore.setCurrentScreen('collage');
  } catch (error) {
    console.error('Failed to upload photo:', error);
  }
};

const onCollageSelected = async (collageId: number) => {
  if (!sessionStore.sessionToken) {
    console.error('No session token available');
    return;
  }

  // Устанавливаем состояние загрузки
  uiStore.setCurrentScreen('processing');

  try {
    const response = await apiService.createOrder(sessionStore.sessionToken, collageId);
    orderStore.setOrder(response.data.order_id);

    // После получения ответа от сервера (когда генерация завершена),
    // проверяем статус и переходим к соответствующему экрану
    if (response.data.status === 'ready_blurred' || response.data.status === 'ready') {
      // Если изображение уже готово, переходим к размытому результату
      // Нужно получить URL размытого изображения
      const orderDetails = await apiService.getOrderStatus(response.data.order_id);
      if (orderDetails.data.blurred_image_url) {
        orderStore.setBlurredImage(orderDetails.data.blurred_image_url);
      }
      uiStore.setCurrentScreen('blurred');
    } else {
      // Если статус другой, остаемся на экране обработки или переходим к соответствующему состоянию
      uiStore.setCurrentScreen('processing');
    }

    // TODO: Implement WebSocket listener for order updates
  } catch (error) {
    console.error('Failed to create order:', error);
    // В случае ошибки можно вернуться на предыдущий экран или показать сообщение об ошибке
    // Пока просто выводим ошибку в консоль
  }
};

const initiatePayment = async () => {
  // Инициируем оплату через Альфа-банк и сразу переходим к экрану оплаты
  // Это позволяет избежать выбора способа оплаты, т.к. используется только QR-код Альфа-банка
  uiStore.setCurrentScreen('payment');
};

const onPaymentInitiated = () => {
  // Payment initiated, wait for webhook/WS to update order status
  // Do not set status to 'paid' immediately - let the status checking mechanism handle it
  // The payment status will be checked by PaymentOptions component
};

const onPaymentSuccess = () => {
  // Payment confirmed successfully, move to delivery screen
  orderStore.updateStatus('paid');
  uiStore.setCurrentScreen('delivery');
};

const onDeliverySent = () => {
  uiStore.setCurrentScreen('thank-you');

  // Auto-redirect to home after 5 seconds
  setTimeout(() => {
    uiStore.setCurrentScreen('home');
  }, 5000);
};

onMounted(() => {
  // Initialize the app to the home screen
  uiStore.setCurrentScreen('home');
});
</script>
