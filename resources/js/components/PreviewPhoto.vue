<template>
  <div class="flex flex-col items-center justify-center h-full">
    <div v-if="photo" class="w-full max-w-2xl">
      <img :src="photo" alt="Preview" class="max-h-[70vh] rounded-xl border-4 border-white shadow-xl" />
    </div>
    <div v-else class="w-full max-w-2xl flex items-center justify-center">
      <div class="text-white text-2xl text-center">Не удалось получить доступ к камере</div>
    </div>
    <div class="mt-8 flex space-x-6">
      <button
        @click="onRetake"
        class="bg-gray-500 text-white text-3xl font-bold py-4 px-8 rounded-full shadow-lg hover:bg-gray-600 transition-colors"
      >
        Переделать
      </button>
      <button
        @click="onConfirm"
        :disabled="!photo"
        class="bg-green-600 text-white text-3xl font-bold py-4 px-8 rounded-full shadow-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      >
        Оставить
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  photo: string;
}>();

const emit = defineEmits(['retake', 'confirm']);

const onRetake = () => {
  emit('retake');
};

const onConfirm = () => {
  emit('confirm');
};
</script>
