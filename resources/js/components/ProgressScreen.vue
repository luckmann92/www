<template>
  <div class="flex flex-col items-center justify-center h-full">
    <!-- Индикатор загрузки -->
    <div class="w-full max-w-2xl bg-gray-200 rounded-full h-8 mb-8">
      <div
        class="bg-gradient-to-r from-blue-500 to-purple-600 h-8 rounded-full transition-all duration-500 ease-out"
        :style="{ width: progress + '%' }"
      ></div>
    </div>

    <!-- Текст статуса -->
    <p class="text-4xl text-white text-center mb-8">{{ progressText }}</p>

    <!-- Дополнительная информация -->
    <div class="text-center text-gray-300">
      <p class="text-xl">Подождите, генерируем ваше ИИ-изображение...</p>
      <p class="text-lg mt-2">Это может занять несколько минут</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia';
import { useUiStore } from '../stores/ui';

const uiStore = useUiStore();
const { progress, progressText } = storeToRefs(uiStore);
</script>
