<?php

declare(strict_types=1);

namespace App\Monitoring\Runner;

use App\Monitoring\Entity\Check;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface CheckRunnerInterface
{
    public function run(Check $check): CheckOutcome;
}
