<template>
  <div class="bg-gray-900 text-white h-screen w-screen flex flex-col">
    <!-- Main content area -->
    <div class="flex-grow flex items-center justify-center p-8">
      <HeroSlider v-if="uiStore.currentScreen === 'home'" @start="startSession" />
      <CameraCapture v-else-if="uiStore.currentScreen === 'camera'" @photo-captured="onPhotoCaptured" />
      <PreviewPhoto v-else-if="uiStore.currentScreen === 'preview'" :photo="currentPhoto" @retake="retakePhoto" @confirm="confirmPhoto" />
      <CollageSelect v-else-if="uiStore.currentScreen === 'collage'" @collage-selected="onCollageSelected" />
      <ProgressScreen v-else-if="uiStore.currentScreen === 'processing'" />
      <BlurredResult v-else-if="uiStore.currentScreen === 'blurred'" :imageUrl="orderStore.blurredImageUrl || ''" @unlock-requested="initiatePayment" />
      <PaymentOptions v-else-if="uiStore.currentScreen === 'payment'" @payment-initiated="onPaymentInitiated" />
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

  try {
    const response = await apiService.createOrder(sessionStore.sessionToken, collageId);
    orderStore.setOrder(response.data.order_id);
    uiStore.setCurrentScreen('processing');

    // TODO: Implement WebSocket listener for order updates
  } catch (error) {
    console.error('Failed to create order:', error);
  }
};

const initiatePayment = () => {
  uiStore.setCurrentScreen('payment');
};

const onPaymentInitiated = () => {
  // Payment initiated, wait for webhook/WS to update order status
  // For now, simulate a successful payment and move to delivery
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
