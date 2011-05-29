<?php
require_once '../libs/CloudCache/CloudCacheNormal.php';

/**
 * Test Case For Interface Implement Class
 *
 */
class CloudCacheNormalTest extends PHPUnit_Framework_TestCase
{
    //contains the object handle of the CloudCacheNormalTest class
    var $cache;

    // called before the test functions will be executed
    // this function is defined in PHPUnit_TestCase and overwritten
    // here
    function setUp() {
        // create a new instance of String with the
        $this->cache = new CloudCacheNormal('', '', '');
    }

    // called after the test functions are executed
    // this function is defined in PHPUnit_TestCase and overwritten
    // here
    function tearDown() {
        // delete your instance
        unset($this->cache);
    }

    function testAuth(){
        //TODO
    }
    function testBadAuth(){
        //TODO
    }

    function testBasicOperation() {
        $to_put = 'I am a testing string. Take me apart and put me back together again.';
        $this->cache->put('s1',$to_put);
        $this->assertEquals($to_put, $this->cache->get('s1'));
    }

    function testID_NotExists() {
        $this->assertNull($this->cache->get('does_not_exist',true));
        $this->assertNull($this->cache->get('does_not_exist'));
    }

    function testDelete() {
        $to_put = 'I am a testing string. Take me apart and put me back together again.';
        $this->cache->put('will_delete',$to_put);
        sleep(1);
        $this->assertEquals($to_put, $this->cache->get('will_delete'));

        $this->cache->delete('will_delete');
    }

    function testItemExpiration() {
        $to_put = 'I am a testing string. Take me apart and put me back together again.';
        /*
         * This time out is a test of both the API and CloudCache.  The idea is:
         * Of the time to send the PUT and recive a response 1/2 can be discarded
         * because that is travel time to CloudCache
         * Assuming that the time to send the GET will be the same we can send
         * the get before the time out is fully up, assuming that it arives
         * at CloudCache after the timeout.
         *
        */
        $time_to_wait = 5;  //in seconds

        list($utime, $time) = explode(" ", microtime());
        $start_put = ((float)$utime + (float)$time);

        $this->cache->put('will_expire',$to_put,$time_to_wait);

        list($utime, $time) = explode(" ", microtime());
        $end_put = ((float)$utime + (float)$time);

        $this->assertEquals($to_put, $this->cache->get('will_expire'));
        list($utime, $time) = explode(" ", microtime());
        $left_to_wait = $time_to_wait - (((float)$utime + (float)$time) - $start_put) - ( $end_put - $start_put )/2;
        //Sleep for the remaining time
        usleep($left_to_wait * 1000000);

        $this->assertNull($this->cache->get('will_expire'),'The time tolerance here is thin. I only care if this always fails');
    }

    function testList_Keys() {
        $this->cache->put('k1','data');
        sleep(1);
        $keys = $this->cache->list_keys();
        $this->assertTrue(is_array($keys));
        $this->assertTrue(array_key_exists('k1', $keys));
    }

    function testFlush() {
        $this->cache->put('something_to_flush','data');
        sleep(1);
        $keys = $this->cache->list_keys();

        $this->cache->flush();
        //No Sleep.
        $keys = $this->cache->list_keys();
        $this->assertTrue(is_array($keys));
        $this->assertEquals(0, count($keys));
    }

    function testGet_Multi() {

        $to_test = array(
            'one' => 'SomeData one',
            'two' => 'SomeData two',
            'three' => 'SomeData three',
            'four' => 'SomeData four',
            'five' => 'SomeData five'
        );
        foreach ( $to_test as $key => $value ) {
            $this->cache->put($key, $value);
        }

        $ret = $this->cache->get_multi(array('one','two','three','four','five'));

        $this->assertEquals(count($to_test), count($ret));

        foreach ( $to_test as $key => $value ) {
            $this->assertEquals($value, $ret[$key]);
        }

    }

    function testIncrementDecrement(){
        $this->cache->put('incrment_test',0);
        $val = $this->cache->incr('incrment_test');
        $this->assertEquals(1,$val);

        for ($i=1;$i<10;$i++) {
            $val = $this->cache->incr('incrment_test');
            $this->assertEquals($i+1,$val);
        }

        for ($i=$val;$i;$i--) {
            $val = $this->cache->decr('incrment_test');
            $this->assertEquals($i-1,$val);
        }

        $val = $this->cache->decr('incrment_test');
        $this->assertEquals(0,$val);

        $this->cache->put('incrment_test',0);
        $val = $this->cache->incr('incrment_test');
        $this->assertEquals(1,$val);

        for ($i=3;$i<10;$i+=2) {
            $val = $this->cache->incr('incrment_test',2);
            $this->assertEquals($i,$val);
        }

        for ($i=$val-2;$i>0;$i-=2) {
            $val = $this->cache->decr('incrment_test',2);
            $this->assertEquals($i,$val);
        }
        
    }

    
}
?>

