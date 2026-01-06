import axios from 'axios';

const API_BASE_URL = '/api';

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 120000, // 30 seconds timeout for general API requests
});

export const apiService = {
  // Session
  startSession: (deviceId) => apiClient.post('/session/start', { device_id: deviceId }),
  endSession: (sessionId) => apiClient.post('/session/end', { session_id: sessionId }),

  // Photo
 uploadPhoto: (sessionToken, photo) => apiClient.post('/photo/upload', { session_token: sessionToken, photo }),

  // Collages
  getCollages: () => apiClient.get('/collages'),

  // Order
  createOrder: (sessionToken, collageId) => apiClient.post('/order', { session_token: sessionToken, collage_id: collageId }),
  getOrderStatus: (orderId) => apiClient.get(`/order/${orderId}`),

 // Payment
   initPayment: (orderId, method) => apiClient.post('/payment/init', { order_id: orderId, method }),
   checkPaymentStatus: (orderId) => apiClient.get(`/payment/status/${orderId}`),
   sendDeliveryEmail: (orderId, email) => apiClient.post(`/order/${orderId}/delivery/email`, { email }),
   sendDeliveryPrint: (orderId) => apiClient.post(`/order/${orderId}/delivery/print`),

  // Telegram QR
  generateTelegramQr: (orderId) => apiClient.post(`/order/${orderId}/telegram-qr`),
};
