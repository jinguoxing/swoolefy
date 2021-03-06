<?php
/**
+----------------------------------------------------------------------
| swoolefy framework bases on swoole extension development, we can use it easily!
+----------------------------------------------------------------------
| Licensed ( https://opensource.org/licenses/MIT )
+----------------------------------------------------------------------
| Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
+----------------------------------------------------------------------
*/

namespace Swoolefy\Rpc;

include_once SWOOLEFY_CORE_ROOT_PATH.'/MainEventInterface.php';

use Swoolefy\Tcp\TcpServer;
use Swoolefy\Core\RpcEventInterface;

abstract class RpcServer extends TcpServer implements RpcEventInterface {
	/**
	 * __construct 初始化
	 * @param array $config
	 */
	public function __construct(array $config = []) {
		parent::__construct($config);
	}

	/**
	 * onWorkerStart worker进程启动时回调处理
	 * @param  object $server
	 * @param  int    $worker_id
	 * @return void       
	 */
	public abstract function onWorkerStart($server, $worker_id);

	/**
	 * onConnet socket连接上时回调函数
	 * @param  object $server
	 * @param  int    $fd    
	 * @return void        
	 */
	public abstract function onConnet($server, $fd);

	/**
	 * onReceive 接收数据时的回调处理，$data是一个完整的数据包，底层已经封装好，只需要配置好，直接使用即可
	 * @param  object $server
	 * @param  int    $fd
	 * @param  int    $reactor_id
	 * @param  mixed  $data
	 * @return boolean
	 */
	public function onReceive($server, $fd, $reactor_id, $data) {
        $app_conf = \Swoolefy\Core\Swfy::getAppConf();
        $appInstance = new \Swoolefy\Rpc\RpcHander($app_conf);
        $appInstance->run($fd, $data);
        return true;
	}

	/**
	 * onTask 任务处理函数调度
	 * @param   object  $server
	 * @param   int     $task_id
	 * @param   int     $from_worker_id
	 * @param   mixed   $data
     * @param   mixed   $task
	 * @return  boolean
	 */
	public function onTask($server, $task_id, $from_worker_id, $data, $task = null) {
		list($callable, $taskData, $fd) = $data;
        $app_conf = \Swoolefy\Core\Swfy::getAppConf();
        $appInstance = new \Swoolefy\Rpc\RpcHander($app_conf);
        $appInstance->run($fd, [$callable, $taskData], [$from_worker_id, $task_id, $task]);
		return true;
	}

	/**
	 * onFinish 异步任务完成后调用
	 * @param    int     $task_id
	 * @param    mixed   $data
	 * @return   void
	 */
	public abstract function onFinish($server, $task_id, $data);

	/**
	 * onPipeMessage 
	 * @param    object  $server
	 * @param    int     $src_worker_id
	 * @param    mixed   $message
	 * @return   void
	 */
	public abstract function onPipeMessage($server, $from_worker_id, $message);

	/**
	 * onClose tcp连接关闭时回调函数
	 * @param  object $server
	 * @param  int    $fd    
	 * @return void
	 */
	public abstract function onClose($server, $fd);
	
}
