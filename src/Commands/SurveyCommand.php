<?php

namespace Nikoleesg\Survey\Commands;

use Illuminate\Console\Command;

class SurveyCommand extends Command
{
    public $signature = 'survey';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
