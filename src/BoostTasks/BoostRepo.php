<?php

/*
 * Copyright 2015 Daniel James <daniel@calamity.org.uk>.
 *
 * Distributed under the Boost Software License, Version 1.0. (See accompanying
 * file LICENSE_1_0.txt or copy at http://www.boost.org/LICENSE_1_0.txt)
 */

namespace BoostTasks;

use BoostTasks\Settings;
use BoostTasks\Repo;
use BoostTasks\BoostRepo;
use BoostTasks\Log;
use RuntimeException;

class BoostRepo extends Repo {
    function __construct() {
        parent::__construct('boost', 'master',
            Settings::dataPath('repos').'/boost');
    }
}
