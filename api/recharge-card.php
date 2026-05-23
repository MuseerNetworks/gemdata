<?php
require_once __DIR__ . '/../includes/bootstrap.php';
try { app(\GemData\Classes\Response::class)->json('success', 'Transaction successful', app(\GemData\Classes\ApiHandler::class)->handlePurchase('recharge_card')); } catch (InvalidArgumentException $e) { app(\GemData\Classes\Response::class)->json('error', 'Validation failed', [], json_decode((string) $e->getMessage(), true) ?: [], [], 422); } catch (Throwable $e) { app(\GemData\Classes\Response::class)->json('error', public_api_error_message($e), [], [], [], 400); }
