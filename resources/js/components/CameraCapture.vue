<template>
  <div class="flex flex-col items-center justify-center h-full">
    <div class="relative w-full max-w-4xl">
      <video
        ref="videoRef"
        autoplay
        muted
        class="w-full h-auto rounded-xl border-4 border-white"
      ></video>
      <div class="absolute top-4 left-1/2 transform -translate-x-1/2 bg-black bg-opacity-50 text-white text-2xl px-4 py-2 rounded-full">
        {{ timeLeft }} сек
      </div>
      <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-black bg-opacity-50 text-white text-2xl px-4 py-2 rounded-full">
        {{ currentPhrase }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const videoRef = ref<HTMLVideoElement | null>(null);
const timeLeft = ref(2);
const currentPhrase = ref('Приготовься к снимку!');
const phrases = [
  'Приготовься к снимку!',
  'Улыбнись!',
  'Камера включена!',
  'Готовимся к шедевру!',
];
let intervalId: number | null = null;
let countdownId: number | null = null;
let phraseIndex = 0;

const emit = defineEmits(['photo-captured']);

const startTimer = () => {
  phraseIndex = 0;
  currentPhrase.value = phrases[phraseIndex];
  timeLeft.value = 2;

  countdownId = window.setInterval(() => {
    timeLeft.value--;
    if (timeLeft.value <= 0) {
      clearInterval(countdownId!);
      capturePhoto();
    }
  }, 1000);

  intervalId = window.setInterval(() => {
    phraseIndex = (phraseIndex + 1) % phrases.length;
    currentPhrase.value = phrases[phraseIndex];
  }, 3000);
};

const capturePhoto = () => {
  if (videoRef.value) {
    const canvas = document.createElement('canvas');
    canvas.width = videoRef.value.videoWidth;
    canvas.height = videoRef.value.videoHeight;
    const ctx = canvas.getContext('2d');
    if (ctx) {
      ctx.drawImage(videoRef.value, 0, 0, canvas.width, canvas.height);
      const imageData = canvas.toDataURL('image/jpeg');
      emit('photo-captured', imageData);
    }
 }
};

onMounted(async () => {
 try {
    // Проверяем наличие API и доступность в текущем контексте
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error('Camera API is not supported in this browser or context');
    }

    const stream = await navigator.mediaDevices.getUserMedia({ video: true });
    if (videoRef.value) {
      videoRef.value.srcObject = stream;
    }
    startTimer();
  } catch (err) {
    console.error('Error accessing camera:', err);
    // Вызываем событие с пустым фото, чтобы приложение могло корректно обработать ситуацию
    emit('photo-captured', '');
  }
});

onUnmounted(() => {
  if (intervalId) clearInterval(intervalId);
  if (countdownId) clearInterval(countdownId);
  if (videoRef.value?.srcObject) {
    const tracks = (videoRef.value.srcObject as MediaStream).getTracks();
    tracks.forEach(track => track.stop());
  }
});
</script>
