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

namespace Swoolefy\Core;

use Swoolefy\Core\Swfy;
use Swoolefy\Core\AppDispatch;
use Swoolefy\Core\Application;

class ServiceDispatch extends AppDispatch {
	/**
	 * $callable 远程调用函数对象类
	 * @var array
	 */
	protected $callable = [];

	/**
	 * $params 远程调用参数
	 * @var null
	 */
	protected $params = null;

	/**
	 * $deny_actions 禁止外部直接访问的action
	 * @var array
	 */
	protected static $deny_actions = ['__construct','_beforeAction','_afterAction','__destruct'];

	/**
	 * __construct 
	 */
	public function __construct($callable, $params, $rpc_pack_header = []) {
		// 执行父类
		parent::__construct();
		$this->callable = $callable;
		$this->params = $params;
		Application::getApp()->mixed_params = $params;
		Application::getApp()->rpc_pack_header = $rpc_pack_header;
	}

	/**
	 * dispatch 路由调度
	 * @return  mixed
	 */
	public function dispatch() {
		list($class, $action) = $this->callable;
        $class = trim(str_replace('\\', '/', $class), '/');
		if(!isset(self::$routeCacheFileMap[$class])) {
			if(!$this->checkClass($class)) {
			    if(Swfy::isWorkerProcess()) {
                    $app_conf = Swfy::getAppConf();
                    if(isset($app_conf['not_found_handler']) && is_string($app_conf['not_found_handler'])) {
                        $handler = $app_conf['not_found_handler'];
                        $notFoundInstance = new $handler;
                        if($notFoundInstance instanceof \Swoolefy\Core\NotFound) {
                            $return_data = $notFoundInstance->return404($class);
                        }
                    }else {
                        $notFoundInstance = new \Swoolefy\Core\NotFound();
                        $return_data = $notFoundInstance->return404($class);
                    }
                }
                // 记录错误异常
                $msg = isset($return_data['msg']) ? $return_data['msg'] : "when dispatch, {$class} not found!";
                $exceptionClass = Application::getApp()->getExceptionClass();
                $exceptionClass::shutHalt($msg);
                return false;
			}
		}
		
		$class = str_replace('/','\\', $class);
		$serviceInstance = new $class();
		$serviceInstance->mixed_params = $this->params;
		if(isset($this->from_worker_id) && isset($this->task_id)) {
            $serviceInstance->setFromWorkerId($this->from_worker_id);
            $serviceInstance->setTaskId($this->task_id);
            if(!empty($this->task)) {
                $serviceInstance->setTask($this->task);
            }
        }

		try{
			if(method_exists($serviceInstance, $action)) {
                $isContinueAction = $serviceInstance->_beforeAction();
                if($isContinueAction === false) {
                    // end
                    return false;
                }
                // next call
				$serviceInstance->$action($this->params);
			}else {
			    if(Swfy::isWorkerProcess()) {
                    $app_conf = Swfy::getAppConf();
                    if(isset($app_conf['not_found_handler']) && is_string($app_conf['not_found_handler'])) {
                        $handle = $app_conf['not_found_handler'];
                        $notFoundInstance = new $handle;
                        if($notFoundInstance instanceof \Swoolefy\Core\NotFound) {
                            $return_data = $notFoundInstance->return500($class, $action);
                        }
                    }else {
                        $notFoundInstance = new \Swoolefy\Core\NotFound();
                        $return_data = $notFoundInstance->return500($class, $action);
                    }
                }
                // 记录错误异常
                $msg = isset($return_data['msg']) ? $return_data['msg'] : "when dispatch, {$class} call undefined function {$action}()";
                $exceptionClass = Application::getApp()->getExceptionClass();
                $exceptionClass::shutHalt($msg);
				return false;
			}
		}catch(\Throwable $t) {
			$msg = $t->getMessage().' on '.$t->getFile().' on line '.$t->getLine().' ||| '.$class.'::'.$action.' ||| '.json_encode($this->params,JSON_UNESCAPED_UNICODE);
			$app_conf = Swfy::getAppConf();
			if(Swfy::isWorkerProcess()) {
                if(isset($app_conf['not_found_handler']) && is_string($app_conf['not_found_handler'])) {
                    $handle = $app_conf['not_found_handler'];
                    $notFoundInstance = new $handle;
                    if($notFoundInstance instanceof \Swoolefy\Core\NotFound) {
                        $return_data = $notFoundInstance->returnError($msg);
                    }
                }else {
                    $notFoundInstance = new \Swoolefy\Core\NotFound();
                    $return_data = $notFoundInstance->returnError($msg);
                }
            }
            // 记录错误异常
            $exceptionClass = Application::getApp()->getExceptionClass();
            $exceptionClass::shutHalt($msg);
            return false;
		}
		
	}

    /**
     * @param int $from_worker_id
     * @param int $task_id
     */
	public function setFromWorkerIdAndTaskId(int $from_worker_id, int $task_id, $task = null) {
	    $this->from_worker_id = $from_worker_id;
	    $this->task_id = $task_id;
	    $this->task = $task;
    }

	/**
	 * checkClass 检查请求实例文件是否存在
	 * @param  string  $class
	 * @return boolean
	 */
	public function checkClass($class) {
        if(isset(self::$routeCacheFileMap[$class])) {
            return true;
        }
		$file = ROOT_PATH.DIRECTORY_SEPARATOR.$class.'.php';
		if(is_file($file)) {
			self::$routeCacheFileMap[$class] = true;
			return true;
		}
		return false;
	}

}