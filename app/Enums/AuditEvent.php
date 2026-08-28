<?php

namespace App\Enums;

enum AuditEvent: string
{
    case LoginSuccess = 'login.success';
    case LoginFailed = 'login.failed';
    case LoginLocked = 'login.locked';
    case Logout = 'logout';
    case TwoFactorChallenged = '2fa.challenged';
    case TwoFactorFailed = '2fa.failed';
    case TwoFactorReset = '2fa.reset';
    case RoleAssigned = 'role.assigned';
    case RoleRevoked = 'role.revoked';
    case AccountSuspended = 'account.suspended';
    case TokenRevoked = 'token.revoked';
    case BreakGlass = 'break_glass';
    case BreakGlassRelock = 'break_glass.relock';
}
