<template>
  <div class="flex flex-col items-center justify-center h-full">
    <h1 class="text-6xl font-bold text-white mb-12 text-center">
      {{ currentSlogan }}
    </h1>
    <button
      @click="onStartClick"
      class="bg-gradient-to-r from-purple-600 to-pink-600 text-white text-4xl font-bold py-6 px-12 rounded-full shadow-lg hover:scale-105 transition-transform duration-300"
    >
      НАЖМИ СТАРТ
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useUiStore } from '../stores/ui';

const uiStore = useUiStore();
let intervalId: number | null = null;

const currentSlogan = ref(uiStore.slogans[uiStore.currentSloganIndex]);

const updateSlogan = () => {
  uiStore.nextSlogan();
  currentSlogan.value = uiStore.slogans[uiStore.currentSloganIndex];
};

const onStartClick = () => {
  // Emit event to parent to handle session start
  emit('start');
};

// Auto-rotate slogans
onMounted(() => {
  intervalId = setInterval(updateSlogan, 3000);
});

onUnmounted(() => {
  if (intervalId) {
    clearInterval(intervalId);
  }
});

// Define emits
const emit = defineEmits(['start']);
</script>
