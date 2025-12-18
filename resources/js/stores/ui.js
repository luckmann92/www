import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
  state: () => ({
    currentScreen: 'home', // home, camera, preview, collage, processing, blurred, payment, delivery, thank-you
    slogans: [
      'Создай своё ИИ-чудо!',
      'Магия начинается здесь!',
      'Ты — главный герой снимка!',
    ],
    currentSloganIndex: 0,
    progress: 0,
    progressText: 'Загрузка...',
  }),

  actions: {
    setCurrentScreen(screen) {
      this.currentScreen = screen;
    },

    nextSlogan() {
      this.currentSloganIndex = (this.currentSloganIndex + 1) % this.slogans.length;
    },

    setProgress(value, text = null) {
      this.progress = value;
      if (text) this.progressText = text;
    },
  },
});
