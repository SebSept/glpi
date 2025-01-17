<?php

declare(strict_types=1);

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\Kernel\Listener;

use Glpi\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class LoadPlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['handlePluginExceptions', 2],
//            ConsoleErrorEvent::class => ['handlePluginExceptionsConsole', 2], // @todo test behavior in console, maybe implement this
        ];
    }

    public function handlePluginExceptions(ExceptionEvent $event): void
    {
//         return; // usefull to have stack track
        $throwable = $event->getThrowable();

        $plugin_key = $this->getPluginKeyFromFilePath($throwable->getFile());
        if (is_null($plugin_key)) {
            // not a plugin error since path is not related to a plugin
            return;
        }

        $this->suspendPlugin($plugin_key);

        // display error message using trigger_error
        // setResponse to return a 500 error

        // /!\ Do not trigger E_COMPILE_ERROR or an error that stop the current script
        // E_USER_WARNING is probably the most appropriate
        // @see https://www.php.net/manual/en/errorfunc.constants.php#constant.e-user-warning
        trigger_error('Plugin error: ' . $throwable->getMessage(), E_USER_WARNING);
        // @todo this error is not in the files/_log/php-errors.log, maybe because we are currently handling an exception...


        $event->setResponse(new Response(
            'Plugin "' . $plugin_key . '" Fatal error: ' . $throwable->getMessage(),
            Response::HTTP_INTERNAL_SERVER_ERROR
        ));

        // log error @todo injectable ?
        $message = sprintf(
            __('Plugin %1$s has been suspended by system, it has fatal errors.'),
            $plugin_key
        );
        Event::log(
            '',
            \Plugin::class,
            3,
            "setup",
            $message
        );

        // message de session - @todo utile ? @todo injectable ?
        \Session::addMessageAfterRedirect(
            $message,
            true,
            ERROR
        );

        // maybe we could make a redirection here.
        // but it's probably better to let the user see the error message
        // In case, we do it we should do something to avoir risk of an infinite loop.

        $event->stopPropagation();
    }

    /**
     * Suspend plugin
     *
     * Being suspended, a plugin should not be loaded anymore.
     * If it doesn't exist in database, it will be created,
     * doing so it can be filtered to avoid loading it.
     *
     * @param string $plugin_key
     * @return void
     */
    private function suspendPlugin(string $plugin_key): void
    {
        $plugin = new \Plugin();
        $is_in_database = $plugin->getFromDBbyDir($plugin_key);
        if (!$is_in_database) {
            $creation = $plugin->add(
                [
                    'directory' => $plugin_key,
                    'name' => $plugin_key, // @todo maybe we can get the name from xml, but it's probably better do display the directory to give useful information to the user/developer
                    'version' => '0.0.0',
                    'state' => \Plugin::SUSPENDED,
                ]
            );

            if (!$creation) {
                Event::log(
                    '',
                    \Plugin::class,
                    3,
                    __FILE__,
                    "Failed to create plugin $plugin_key in database for suspension."
                );
                throw new \RuntimeException("Failed to create plugin '$plugin_key' in database for suspension.");
            }
        }

        $plugin->suspend();
    }

    private function getPluginKeyFromFilePath(string $path): ?string
    {
        // @todo maybe convert to lowercase, see Plugin::isXXX (activated, etc.)
        foreach (PLUGINS_DIRECTORIES as $plugin_directory) {
            if (preg_match('#' . preg_quote($plugin_directory, '#') . '/([^/]+)/#', $path, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

}
