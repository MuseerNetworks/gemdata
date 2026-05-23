<?php
require_once __DIR__ . '/../includes/bootstrap.php';
try { app(\GemData\Classes\Response::class)->json('success', 'Balance fetched successfully', app(\GemData\Classes\ApiHandler::class)->balance()); } catch (Throwable $e) { app(\GemData\Classes\Response::class)->json('error', public_api_error_message($e), [], [], [], 400); }
