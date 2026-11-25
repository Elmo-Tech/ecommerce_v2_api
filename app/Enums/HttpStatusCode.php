<?php

declare(strict_types=1);

namespace App\Enums;

enum HttpStatusCode: int
{
    case Ok = 200;
    case Created = 201;
    case Accepted = 202;
    case NoContent = 204;
    case BadRequest = 400;
    case Unauthorized = 401;
    case Forbidden = 403;
    case NotFound = 404;
    case MethodNotAllowed = 405;
    case Conflict = 409;
    case UnprocessableContent = 422;
    case TooManyRequests = 429;
    case InternalServerError = 500;
    case ServiceUnavailable = 503;
}
