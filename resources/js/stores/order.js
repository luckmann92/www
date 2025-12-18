import { defineStore } from 'pinia';

export const useOrderStore = defineStore('order', {
  state: () => ({
    orderId: null,
    status: 'idle', // idle, pending, processing, ready_blurred, paid, delivered
    blurredImageUrl: null,
    fullImageUrl: null,
  }),

  actions: {
    setOrder(id) {
      this.orderId = id;
      this.status = 'pending';
    },

    updateStatus(status) {
      this.status = status;
    },

    setBlurredImage(url) {
      this.blurredImageUrl = url;
    },

    setFullImage(url) {
      this.fullImageUrl = url;
    },
  },
});
