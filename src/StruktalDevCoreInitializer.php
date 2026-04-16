<?php

namespace struktal\core;

use Composer\Script\Event;

class StruktalDevCoreInitializer {
    public static function installPlaywrightDependencies(Event $event) {
        if (!$event->isDevMode()) {
            $event->getIO()->write("<info>⏩ [STRUKTAL] Skipping Playwright dependency installation (not in dev mode)</info>");
            return;
        }

        $event->getIO()->write("<info>⏳ [STRUKTAL] Installing Playwright dependencies...</info>");

        $commands = [
            "vendor/bin/playwright-install --browsers",
            "vendor/bin/playwright-install --with-deps"
        ];

        foreach ($commands as $command) {
            $event->getIO()->write("<comment>🤖 [STRUKTAL] $command</comment>");
            passthru($command, $exitCode);
            if ($exitCode !== 0) {
                $event->getIO()->write("<error>❌ [STRUKTAL] Command failed with exit code $exitCode: $command</error>");
                return;
            }
        }

        $event->getIO()->write("<info>✅ [STRUKTAL] Playwright dependencies installed successfully</info>");
    }
}
