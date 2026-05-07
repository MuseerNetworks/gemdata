const crypto = require('crypto');
const { pool, withTransaction } = require('./db');

function toKobo(amount) {
  return Math.round(Number(amount) * 100);
}

function normalizeAmount(amount) {
  return Number(Number(amount).toFixed(2));
}

function buildReference() {
  return `vtu_fund_${Date.now()}_${crypto.randomBytes(6).toString('hex')}`;
}

async function findUserByEmail(email) {
  const [rows] = await pool.execute(
    'SELECT id, email, balance FROM users WHERE email = ? LIMIT 1',
    [email]
  );

  return rows[0] || null;
}

async function createPendingTransaction({ userId, amount, reference, authorizationUrl, accessCode }) {
  const amountValue = normalizeAmount(amount);
  const amountKobo = toKobo(amountValue);

  await pool.execute(
    `INSERT INTO transactions
      (user_id, amount, amount_kobo, reference, status, authorization_url, access_code, currency)
     VALUES (?, ?, ?, ?, 'pending', ?, ?, 'NGN')`,
    [userId, amountValue, amountKobo, reference, authorizationUrl, accessCode]
  );
}

async function markTransactionState(reference, fields) {
  const updates = [];
  const values = [];

  for (const [key, value] of Object.entries(fields)) {
    updates.push(`${key} = ?`);
    values.push(value);
  }

  if (updates.length === 0) {
    return;
  }

  values.push(reference);
  await pool.execute(
    `UPDATE transactions
     SET ${updates.join(', ')}, updated_at = CURRENT_TIMESTAMP
     WHERE reference = ?`,
    values
  );
}

async function processSuccessfulPayment({ reference, paystackData, source }) {
  return withTransaction(async (connection) => {
    const [txRows] = await connection.execute(
      `SELECT t.*, u.email, u.balance
       FROM transactions t
       INNER JOIN users u ON u.id = t.user_id
       WHERE t.reference = ?
       LIMIT 1
       FOR UPDATE`,
      [reference]
    );

    const transaction = txRows[0];
    if (!transaction) {
      return {
        ok: false,
        code: 'TRANSACTION_NOT_FOUND',
        message: 'Transaction reference was not found.'
      };
    }

    if (transaction.credited_at) {
      return {
        ok: true,
        alreadyProcessed: true,
        message: 'Wallet already credited for this reference.',
        transaction
      };
    }

    if (paystackData.status !== 'success') {
      await connection.execute(
        `UPDATE transactions
         SET status = 'failed',
             paystack_status = ?,
             gateway_response = ?,
             verified_at = CURRENT_TIMESTAMP,
             channel = ?
         WHERE id = ?`,
        [paystackData.status, paystackData.gateway_response || null, source, transaction.id]
      );

      return {
        ok: false,
        code: 'PAYMENT_NOT_SUCCESSFUL',
        message: `Payment is currently ${paystackData.status}.`
      };
    }

    if (Number(paystackData.amount) !== Number(transaction.amount_kobo)) {
      await connection.execute(
        `UPDATE transactions
         SET status = 'failed',
             paystack_status = 'amount_mismatch',
             gateway_response = 'Amount mismatch detected during verification',
             verified_at = CURRENT_TIMESTAMP,
             channel = ?
         WHERE id = ?`,
        [source, transaction.id]
      );

      return {
        ok: false,
        code: 'AMOUNT_MISMATCH',
        message: 'Verified amount does not match the expected funding amount.'
      };
    }

    if ((paystackData.customer?.email || '').toLowerCase() !== String(transaction.email).toLowerCase()) {
      await connection.execute(
        `UPDATE transactions
         SET status = 'failed',
             paystack_status = 'email_mismatch',
             gateway_response = 'Customer email mismatch detected during verification',
             verified_at = CURRENT_TIMESTAMP,
             channel = ?
         WHERE id = ?`,
        [source, transaction.id]
      );

      return {
        ok: false,
        code: 'EMAIL_MISMATCH',
        message: 'Verified payment email does not match the wallet owner.'
      };
    }

    const nextBalance = normalizeAmount(transaction.balance + transaction.amount);

    await connection.execute(
      'UPDATE users SET balance = ? WHERE id = ?',
      [nextBalance, transaction.user_id]
    );

    await connection.execute(
      `UPDATE transactions
       SET status = 'success',
           paystack_status = ?,
           gateway_response = ?,
           paystack_transaction_id = ?,
           paid_at = FROM_UNIXTIME(?),
           verified_at = CURRENT_TIMESTAMP,
           credited_at = CURRENT_TIMESTAMP,
           credited_amount = ?,
           channel = ?,
           metadata_json = ?
       WHERE id = ?`,
      [
        paystackData.status,
        paystackData.gateway_response || null,
        paystackData.id || null,
        paystackData.paid_at ? Math.floor(new Date(paystackData.paid_at).getTime() / 1000) : Math.floor(Date.now() / 1000),
        transaction.amount,
        source,
        JSON.stringify(paystackData),
        transaction.id
      ]
    );

    return {
      ok: true,
      alreadyProcessed: false,
      message: 'Wallet credited successfully.',
      walletBalance: nextBalance,
      transaction: {
        reference: transaction.reference,
        amount: transaction.amount
      }
    };
  });
}

module.exports = {
  buildReference,
  createPendingTransaction,
  findUserByEmail,
  markTransactionState,
  processSuccessfulPayment,
  toKobo
};
