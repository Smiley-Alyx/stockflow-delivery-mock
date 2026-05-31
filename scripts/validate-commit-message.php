<?php

declare(strict_types=1);

$messageFile = $argv[1] ?? null;

if ($messageFile === null || ! is_readable($messageFile)) {
    fwrite(STDERR, "Не удалось прочитать файл commit message.\n");

    exit(1);
}

$lines = file($messageFile, FILE_IGNORE_NEW_LINES);

if ($lines === false) {
    fwrite(STDERR, "Не удалось загрузить commit message.\n");

    exit(1);
}

$subject = firstMessageLine($lines);

if ($subject === null) {
    fwrite(STDERR, "Commit message не должен быть пустым.\n");

    exit(1);
}

$allowedTypes = [
    'feat',
    'fix',
    'refactor',
    'perf',
    'test',
    'docs',
    'infra',
    'build',
    'ci',
    'chore',
    'revert',
];

$types = implode('|', $allowedTypes);
$pattern = "/^({$types})\\([a-z0-9][a-z0-9-]*\\)!?: [a-z0-9].{8,99}$/";

if (preg_match($pattern, $subject) !== 1) {
    fwrite(STDERR, invalidMessage($subject, $allowedTypes));

    exit(1);
}

if (str_ends_with($subject, '.')) {
    fwrite(STDERR, "Commit subject не должен заканчиваться точкой.\n");

    exit(1);
}

exit(0);

/**
 * @param  list<string>  $lines
 */
function firstMessageLine(array $lines): ?string
{
    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        return $line;
    }

    return null;
}

/**
 * @param  list<string>  $allowedTypes
 */
function invalidMessage(string $subject, array $allowedTypes): string
{
    return implode("\n", [
        'Commit message не соответствует project policy.',
        '',
        "Получено: {$subject}",
        '',
        'Нужен формат: type(scope): subject',
        'Scope обязателен и пишется kebab-case.',
        'Subject начинается с маленькой латинской буквы или цифры, без точки в конце.',
        'Длина subject: 9-100 символов.',
        'Разрешенные type: '.implode(', ', $allowedTypes).'.',
        '',
        'Примеры:',
        '  feat(shipment-lifecycle): add create and cancel shipment service',
        '  refactor(status-history): extract invalid transition handling',
        '  test(rabbitmq): cover shipment request happy path over amqp',
        '  docs(delivery-flow): describe shipment status progression sequence',
        '  infra(observability): add prometheus metrics endpoint',
        '  chore(bootstrap): initialize delivery mock service',
        '',
    ]);
}
