<?php

declare(strict_types=1);

passthru('git rev-parse --is-inside-work-tree > /dev/null 2>&1', $gitExitCode);

if ($gitExitCode !== 0) {
    fwrite(STDERR, "Git hooks не установлены: директория не является git worktree.\n");

    exit(1);
}

$commands = [
    ['git', 'config', 'core.hooksPath', '.githooks'],
    ['git', 'config', 'commit.template', '.gitmessage'],
];

foreach ($commands as $command) {
    passthru(buildCommand($command), $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, 'Не удалось выполнить: '.implode(' ', $command)."\n");

        exit($exitCode);
    }
}

fwrite(STDOUT, "Git hooks установлены: core.hooksPath=.githooks, commit.template=.gitmessage\n");

function buildCommand(array $parts): string
{
    return implode(' ', array_map('escapeshellarg', $parts));
}
