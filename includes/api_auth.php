<?php

declare(strict_types=1);

use GemData\Classes\ApiAuth;

function api_auth(): ApiAuth
{
    return app(ApiAuth::class);
}
