<?php

namespace struktal\core;

use eftec\bladeone\BladeOne;
use struktal\Auth\Auth;
use struktal\ComposerReader\ComposerReader;
use struktal\Config\StruktalConfig;
use struktal\InfoMessage\InfoMessageHandler;
use struktal\Logger\Logger;
use struktal\Logger\LogLevel;
use struktal\MailWrapper\MailWrapper;
use struktal\ORM\Database\Database;
use struktal\Router\Router;
use struktal\Translator\LanguageUtil;
use struktal\Translator\Translator;
use struktal\validation\ValidationBuilder;

class StruktalCore {
    public static function start(string $appDirectory, ?string $userObjectName = null): void {
        // Autoload Composer libraries
        require_once($appDirectory . "/vendor/autoload.php");

        // ClassLoader
        $classLoader = \struktal\core\internal\ClassLoader::getInstance();

        // Setup utility Composer libraries
        StruktalConfig::setConfigFilePath($appDirectory . "/config/config.json");
        define("Config", new StruktalConfig());

        Logger::setLogDirectory($appDirectory . "/logs/");
        Logger::setMinLogLevel(LogLevel::tryFrom(Config->getLogLevel()) ?? LogLevel::TRACE);
        define("Logger", new Logger("App"));

        // Load project files
        $classLoader->loadDirectory($appDirectory . "/src/lib/");

        // Setup Composer libraries
        Router::setPagesDirectory($appDirectory . "/src/pages/");
        Router::setAppUrl(Config->getAppUrl());
        Router::setAppBaseUri(Config->getBaseUri());
        Router::setStaticDirectoryUri("static/");
        define("Router", new Router());

        if(Config->databaseEnabled()) {
            Database::connect(
                Config->getDatabaseHost(),
                Config->getDatabaseName(),
                Config->getDatabaseUsername(),
                Config->getDatabasePassword()
            );
        }

        if($userObjectName !== null) {
            Auth::setUserObjectName($userObjectName);
            define("Auth", new Auth());
        }

        define("Validation", new ValidationBuilder());

        Translator::setTranslationsDirectory($appDirectory . "/src/translations/");
        Translator::setDomain("messages");
        Translator::setLocale(LanguageUtil::getPreferredLocale());
        define("Translator", new Translator());

        define("Blade", new BladeOne($appDirectory . "/src/templates", $appDirectory . "/template-cache", BladeOne::MODE_DEBUG));

        MailWrapper::setSetupFunction(function(MailWrapper $mailWrapper) {
            $mailWrapper->isSMTP();
            $mailWrapper->Host = Config->getSmtpHost();
            $mailWrapper->Port = Config->getSmtpPort();
            $mailWrapper->SMTPAuth = Config->getSmtpAuth();
            $mailWrapper->Username = Config->getSmtpUsername();
            $mailWrapper->Password = Config->getSmtpPassword();
            $mailWrapper->SMTPSecure = Config->getSmtpSecure();
            $mailWrapper->CharSet = "UTF-8";
        });
        MailWrapper::setRedirectAllMails(
            Config->redirectAllMails(),
            Config->getRedirectMailAddress()
        );

        define("InfoMessage", new InfoMessageHandler());

        ComposerReader::setProjectDirectory($appDirectory);
        define("ComposerReader", new ComposerReader());

        // Override BladeOne's include directive to use components with isolated variables
        Blade->directive("include", function($expression) {
            $code = Blade->phpTag . " Blade->startComponent($expression); ?>";
            $code .= Blade->phpTag . ' echo Blade->renderComponent(); ?>';
            return $code;
        });

        // Setup timezone
        date_default_timezone_set("UTC");

        // Setup logger
        $sendEmailHandler = function(string $formattedMessage, string $serializedMessage, mixed $originalMessage) {
            if(empty(Config->getLogRecipients())) {
                return;
            }

            $mail = new \struktal\MailWrapper\MailWrapper();
            $mail->Subject = "[" . Config->getAppName() . "] Error report";
            $mail->Body = $formattedMessage;
            foreach(Config->getLogRecipients() as $recipient) {
                $mail->addAddress($recipient);
            }
            $mail->send();
        };
        Logger::addCustomLogHandler(LogLevel::ERROR, $sendEmailHandler);
        Logger::addCustomLogHandler(LogLevel::FATAL, $sendEmailHandler);
        unset($sendEmailHandler);

        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            $message = "Error " . $errno . ": ";
            $message .= "\"" . $errstr . "\"";
            $message .= " in " . $errfile . " on line " . $errline;
            try {
                if($errno === E_USER_NOTICE) {
                    Logger->tag("PHP")->info($message);
                    return;
                } else if($errno === E_USER_WARNING) {
                    Logger->tag("PHP")->warn($message);
                    return;
                } else if($errno === E_USER_DEPRECATED) {
                    Logger->tag("PHP")->warn($message);
                    return;
                }

                Logger->tag("PHP")->error($message);
            } catch(\Error|\Exception $e) {
                // If the logger fails, log to the default PHP error log
                error_log($message);
            }

            if(php_sapi_name() === "cli") {
                // In CLI, just exit after logging the error
                exit(1);
            }

            if(Config->isProduction()) {
                // Redirect to error page in production
                Router->redirect(Router->generate("500"));
            } else {
                // Show stack trace screen in development
                echo Blade->run("shells.deverror", [
                    "exceptionName" => "Error " . $errno,
                    "exceptionMessage" => $errstr,
                    "trace" => [
                        [
                            "file" => $errfile,
                            "line" => $errline
                        ]
                    ]
                ]);
            }
        });

        set_exception_handler(function($exception) {
            $message = "Uncaught " . get_class($exception) . ": ";
            $message .= "\"" . $exception->getMessage() . "\"";
            $message .= " in " . $exception->getFile() . " on line " . $exception->getLine();
            $message .= PHP_EOL . $exception->getTraceAsString();

            try {
                Logger->tag("PHP")->fatal($message);
            } catch(\Error|\Exception $e) {
                error_log($message);
            }

            if(php_sapi_name() === "cli") {
                // In CLI, just exit after logging the error
                exit(1);
            }

            if(Config->isProduction()) {
                // Redirect to error page in production
                Router->redirect(Router->generate("500"));
            } else {
                // Show stack trace screen in development
                $trace = $exception->getTrace();
                echo Blade->run("shells.deverror", [
                    "exceptionName" => get_class($exception),
                    "exceptionMessage" => $exception->getMessage(),
                    "trace" => [
                        [
                            "file" => $exception->getFile(),
                            "line" => $exception->getLine()
                        ],
                        ...$trace
                    ]
                ]);
            }
        });

        // Initialize routes
        require_once($appDirectory . "/src/config/app-routes.php");
    }
}
