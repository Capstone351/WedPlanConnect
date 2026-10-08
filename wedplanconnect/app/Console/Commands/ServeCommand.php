<?php

namespace App\Console\Commands;

use App\Support\Network;
use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan serve` with clearer start-up output. The stock message shows
 * "http://0.0.0.0:8000", which browsers cannot open; this prints the addresses that work.
 */
#[AsCommand(name: 'serve')]
class ServeCommand extends BaseServeCommand
{
    protected function flushOutputBuffer()
    {
        if (! $this->serverRunningHasBeenDisplayed && str_contains($this->outputBuffer, 'Development Server (http')) {
            $this->serverRunningHasBeenDisplayed = true;
            $this->printAddresses();
        }

        parent::flushOutputBuffer();
    }

    private function printAddresses(): void
    {
        $port = $this->port();
        $ip = Network::lanIp();
        $listensOnNetwork = ! in_array($this->host(), ['127.0.0.1', 'localhost', '::1'], true);

        $this->components->info('WedPlanConnect is running.');
        $this->line("  On this PC:          <fg=green;options=bold>http://127.0.0.1:{$port}</>");

        if ($listensOnNetwork && $ip) {
            $this->line("  Phones (same Wi-Fi): <fg=green;options=bold>http://{$ip}:{$port}</>   <fg=gray>(QR codes use this)</>");
        } else {
            $this->line('  Phones:              <fg=yellow>not available</> <fg=gray>(start with start-wedplanconnect.bat or connect to Wi-Fi)</>');
        }

        $this->comment('  <fg=yellow;options=bold>Press Ctrl+C to stop the server</>');
        $this->newLine();
    }
}
