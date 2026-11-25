<?php

declare(strict_types=1);

namespace App;

use App\Entity\Check;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface CheckRunnerInterface
{
    public function run(Check $check): CheckOutcome;
}
