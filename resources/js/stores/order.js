import { defineStore } from 'pinia';

export const useOrderStore = defineStore('order', {
  state: () => ({
    orderId: null,
    orderUuid: null,
    status: 'idle', // idle, pending, processing, ready_blurred, paid, delivered
    blurredImageUrl: null,
    fullImageUrl: null,
  }),

  actions: {
    setOrder(id, uuid = null) {
      this.orderId = id;
      if (uuid) {
        this.orderUuid = uuid;
      }
      this.status = 'pending';
    },

    updateStatus(status) {
      this.status = status;
    },

    setOrderUuid(uuid) {
      this.orderUuid = uuid;
    },

    setBlurredImage(url) {
      this.blurredImageUrl = url;
    },

    setFullImage(url) {
      this.fullImageUrl = url;
    },
  },
});
