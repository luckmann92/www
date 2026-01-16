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
    // Error handling state
    error: null,
    errorTitle: 'Ошибка',
    showError: false,
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

    /**
     * Show error dialog with message
     * @param {string} message - Error message to display
     * @param {string} title - Optional title for error dialog
     */
    showErrorMessage(message, title = 'Ошибка') {
      this.error = message;
      this.errorTitle = title;
      this.showError = true;
    },

    /**
     * Hide error dialog
     */
    hideError() {
      this.showError = false;
      this.error = null;
      this.errorTitle = 'Ошибка';
    },

    /**
     * Parse and show error from API response
     * @param {Error|Object} error - Error object from catch block
     * @param {string} fallbackMessage - Fallback message if error parsing fails
     */
    handleApiError(error, fallbackMessage = 'Произошла ошибка. Попробуйте позже.') {
      let message = fallbackMessage;

      if (error?.response?.data?.message) {
        message = error.response.data.message;
      } else if (error?.response?.data?.error) {
        message = error.response.data.error;
      } else if (error?.message) {
        message = error.message;
      }

      // Clean up technical details for user-friendly display
      if (message.includes('Client error:') || message.includes('Server error:')) {
        // Extract user-friendly part or use fallback
        const match = message.match(/Response:\s*(\{.*\})/);
        if (match) {
          try {
            const parsed = JSON.parse(match[1]);
            message = parsed.error || parsed.message || fallbackMessage;
          } catch {
            message = fallbackMessage;
          }
        } else {
          message = fallbackMessage;
        }
      }

      this.showErrorMessage(message);
    },
  },
});
