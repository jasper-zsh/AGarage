<?php
/**
 * Created by PhpStorm.
 * User: nicholas
 * Date: 16-2-21
 * Time: 上午5:12
 */

require_once __DIR__ . '/../app/SaeAppKernel.php';

var_dump(file_put_contents('mysaemc://test.txt', 'test content'));