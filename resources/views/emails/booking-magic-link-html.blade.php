<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso à marcação</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #222; max-width: 36rem; margin: 0 auto; padding: 1.5rem;">
    <p>Olá, {{ $user->name }},</p>
    <p>Clica no link abaixo para iniciar sessão no site de marcação. O link expira em cerca de {{ (int) config('booking.magic_link_ttl_minutes', 60) }} minutos.</p>
    <p style="margin: 1.5rem 0;">
        <a href="{{ $loginUrl }}" style="display: inline-block; background: #198754; color: #fff; text-decoration: none; padding: 0.65rem 1.25rem; border-radius: 0.375rem; font-weight: 600;">Iniciar sessão</a>
    </p>
    <p style="font-size: 0.875rem; color: #666;">Se não pediste este email, ignora esta mensagem.</p>
    <p style="margin-top: 1.5rem;">{{ config('app.name') }}</p>
</body>
</html>
