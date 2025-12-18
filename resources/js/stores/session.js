import { defineStore } from 'pinia';

export const useSessionStore = defineStore('session', {
  state: () => ({
    sessionToken: null,
    sessionId: null,
    status: 'idle', // idle, active, finished
  }),

  actions: {
    setSession(token, id) {
      this.sessionToken = token;
      this.sessionId = id;
      this.status = 'active';
    },

    endSession() {
      this.sessionToken = null;
      this.sessionId = null;
      this.status = 'finished';
    },
  },
});
