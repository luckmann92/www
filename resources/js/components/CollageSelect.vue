<template>
  <div class="flex flex-col items-center justify-center h-full">
    <h2 class="text-4xl font-bold text-white mb-8">Выбери стиль</h2>
    <div class="grid grid-cols-2 gap-6 w-full max-w-6xl">
      <div
        v-for="collage in collages"
        :key="collage.id"
        @click="selectCollage(collage.id)"
        class="cursor-pointer rounded-xl overflow-hidden shadow-xl border-4 border-transparent hover:border-white transition-all"
      >
        <img :src="collage.preview_url" :alt="collage.title" class="w-full h-64 object-cover" />
        <div class="bg-black bg-opacity-50 text-white p-4 text-center">
          {{ collage.title }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { apiService } from '../services/api';

interface Collage {
  id: number;
  title: string;
  preview_url: string;
}

const collages = ref<Collage[]>([]);
const emit = defineEmits(['collage-selected']);

const fetchCollages = async () => {
  try {
    const response = await apiService.getCollages();
    collages.value = response.data;
  } catch (error) {
    console.error('Failed to fetch collages:', error);
  }
};

const selectCollage = (id: number) => {
  emit('collage-selected', id);
};

onMounted(() => {
  fetchCollages();
});
</script>
