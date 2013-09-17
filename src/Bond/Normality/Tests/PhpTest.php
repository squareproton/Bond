<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Tests;

use Bond\Normality\Php;

class PhpTest extends \PHPUnit_Framework_Testcase
{

    public function testIsPhpValid()
    {

        $this->assertTrue( (new Php( "<?php echo 'spanner'; ?>" ))->isValid() );
        $this->assertFalse( (new Php( "<?php echo 'spanner'; \\?>" ))->isValid() );
        $this->assertTrue( (new Php( "<?php echo 'spanner';" ))->isValid() );

        $this->assertFalse( (new Php( "die(", true ))->isValid() );
        $this->assertTrue( (new Php( "die(" ))->isValid() );

        $this->assertFalse( (new Php( ":: not valid php;; \$spanner->monkey ", true ))->isValid() );

    }

}