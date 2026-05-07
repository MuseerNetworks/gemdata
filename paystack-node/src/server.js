require('dotenv').config();

const path = require('path');
const express = require('express');
const { pool } = require('./db');
const { initializeTransaction, verifyTransaction, verifyWebhookSignature } = require('./paystack');
const {
  buildReference,
  createPendingTransaction,
  findUserByEmail,
  markTransactionState,
  processSuccessfulPayment,
  toKobo
} = require('./walletService');

const app = express();
const port = Number(process.env.PORT || 3000);

app.post('/api/webhook', express.raw({ type: 'application/json' }), async (req, res) => {
  try {
    const signature = req.get('x-paystack-signature');
    if (!signature || !verifyWebhookSignature(req.body, signature)) {
      return res.status(401).json({ message: 'Invalid webhook signature.' });
    }

    const event = JSON.parse(req.body.toString('utf8'));

    if (event.event === 'charge.success') {
      await processSuccessfulPayment({
        reference: event.data.reference,
        paystackData: event.data,
        source: 'webhook'
      });
    }

    return res.status(200).json({ received: true });
  } catch (error) {
    console.error('Webhook processing failed:', error);
    return res.status(500).json({ message: 'Webhook processing failed.' });
  }
});

app.use(express.json());
app.use(express.static(path.join(__dirname, '..', 'public')));

app.get('/api/health', async (_req, res) => {
  try {
    await pool.query('SELECT 1');
    res.json({ status: 'ok' });
  } catch (error) {
    res.status(500).json({ status: 'error', message: error.message });
  }
});

app.post('/api/pay', async (req, res) => {
  try {
    const email = String(req.body.email || '').trim().toLowerCase();
    const amount = Number(req.body.amount);

    if (!email || !Number.isFinite(amount) || amount <= 0) {
      return res.status(400).json({ message: 'Email and a valid amount are required.' });
    }

    const user = await findUserByEmail(email);
    if (!user) {
      return res.status(404).json({ message: 'User not found.' });
    }

    const reference = buildReference();
    const paystackResponse = await initializeTransaction({
      email: user.email,
      amountKobo: toKobo(amount),
      reference,
      callbackUrl: process.env.PAYSTACK_CALLBACK_URL || undefined
    });

    if (!paystackResponse.status || !paystackResponse.data?.authorization_url) {
      return res.status(502).json({ message: 'Could not initialize Paystack transaction.' });
    }

    await createPendingTransaction({
      userId: user.id,
      amount,
      reference,
      authorizationUrl: paystackResponse.data.authorization_url,
      accessCode: paystackResponse.data.access_code
    });

    return res.status(200).json({
      authorization_url: paystackResponse.data.authorization_url,
      reference
    });
  } catch (error) {
    console.error('Payment initialization failed:', error.response?.data || error);
    return res.status(500).json({
      message: error.response?.data?.message || 'Payment initialization failed.'
    });
  }
});

app.get('/api/verify/:reference', async (req, res) => {
  try {
    const reference = String(req.params.reference || '').trim();
    if (!reference) {
      return res.status(400).json({ message: 'Transaction reference is required.' });
    }

    const verificationResponse = await verifyTransaction(reference);
    const paystackData = verificationResponse.data;

    if (!verificationResponse.status || !paystackData) {
      return res.status(502).json({ message: 'Invalid verification response from Paystack.' });
    }

    if (paystackData.status !== 'success') {
      await markTransactionState(reference, {
        status: paystackData.status === 'failed' ? 'failed' : 'pending',
        paystack_status: paystackData.status,
        gateway_response: paystackData.gateway_response || null,
        verified_at: new Date()
      });

      return res.status(200).json({
        message: `Payment is currently ${paystackData.status}.`,
        status: paystackData.status
      });
    }

    const result = await processSuccessfulPayment({
      reference,
      paystackData,
      source: 'verify'
    });

    if (!result.ok) {
      return res.status(409).json(result);
    }

    return res.status(200).json({
      message: result.alreadyProcessed ? 'Payment already verified and wallet already credited.' : 'Payment verified and wallet credited.',
      reference,
      walletBalance: result.walletBalance
    });
  } catch (error) {
    console.error('Payment verification failed:', error.response?.data || error);
    return res.status(500).json({
      message: error.response?.data?.message || 'Payment verification failed.'
    });
  }
});

app.listen(port, () => {
  console.log(`Paystack wallet server running on http://localhost:${port}`);
});
