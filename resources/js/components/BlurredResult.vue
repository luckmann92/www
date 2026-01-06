<template>
  <div class="flex flex-col items-center justify-center h-full">
    <img :src="imageUrl" alt="Blurred Result" class="max-h-[70vh] rounded-xl border-4 border-white shadow-xl" />
    <p class="text-3xl text-white text-center mt-6 mb-8">
      Открой шедевр — всего за {{ price }}₽
    </p>
    <button
      @click="onUnlockClick"
      class="bg-gradient-to-r from-yellow-500 to-red-600 text-white text-3xl font-bold py-4 px-8 rounded-full shadow-lg hover:scale-105 transition-transform"
    >
      ОПЛАТИТЬ
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  imageUrl: string;
}

defineProps<Props>();

const price = computed(() => {
  // In a real app, this might come from settings or the order itself
  return 250;
});

const emit = defineEmits(['payment-requested']);

const onUnlockClick = () => {
  // Эмитим событие для прямого перехода к оплате QR-кодом Альфа-банка
  // Вызывающий компонент должен перенаправить пользователя сразу на экран оплаты
  emit('payment-requested');
};
</script>
