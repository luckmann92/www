import { defineStore } from 'pinia';

export const useOrderStore = defineStore('order', {
  state: () => ({
    orderId: null,
    orderUuid: null,
    orderCode: null,
    status: 'idle', // idle, pending, processing, ready_blurred, paid, delivered
    blurredImageUrl: null,
    fullImageUrl: null,
  }),

  actions: {
    setOrder(id, uuid = null, code = null) {
      this.orderId = id;
      if (uuid) {
        this.orderUuid = uuid;
      }
      if (code) {
        this.orderCode = code;
      }
      this.status = 'pending';
    },

    updateStatus(status) {
      this.status = status;
    },

    setOrderUuid(uuid) {
      this.orderUuid = uuid;
    },

    setOrderCode(code) {
      this.orderCode = code;
    },

    setBlurredImage(url) {
      this.blurredImageUrl = url;
    },

    setFullImage(url) {
      this.fullImageUrl = url;
    },
  },
});
