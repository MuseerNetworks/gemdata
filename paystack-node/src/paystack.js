const axios = require('axios');
const crypto = require('crypto');

if (!process.env.PAYSTACK_SECRET_KEY) {
  throw new Error('PAYSTACK_SECRET_KEY is required.');
}

const paystackClient = axios.create({
  baseURL: 'https://api.paystack.co',
  headers: {
    Authorization: `Bearer ${process.env.PAYSTACK_SECRET_KEY}`,
    'Content-Type': 'application/json'
  },
  timeout: 15000
});

async function initializeTransaction({ email, amountKobo, reference, callbackUrl }) {
  const payload = {
    email,
    amount: String(amountKobo),
    reference
  };

  if (callbackUrl) {
    payload.callback_url = callbackUrl;
  }

  const response = await paystackClient.post('/transaction/initialize', payload);
  return response.data;
}

async function verifyTransaction(reference) {
  const response = await paystackClient.get(`/transaction/verify/${encodeURIComponent(reference)}`);
  return response.data;
}

function verifyWebhookSignature(rawBody, signature) {
  const expectedSignature = crypto
    .createHmac('sha512', process.env.PAYSTACK_SECRET_KEY)
    .update(rawBody)
    .digest('hex');

  return signature === expectedSignature;
}

module.exports = {
  initializeTransaction,
  verifyTransaction,
  verifyWebhookSignature
};
