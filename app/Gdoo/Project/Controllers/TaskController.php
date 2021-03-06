<?php namespace Gdoo\Project\Controllers;

use Illuminate\Http\Request;

use DB;
use Validator;
use Auth;
use Session;

use Gdoo\User\Models\User;
use Gdoo\Project\Models\Project;
use Gdoo\Project\Models\Task;
use Gdoo\Project\Models\Item;
use Gdoo\Project\Models\Log;
use Gdoo\Index\Models\Attachment;

use Gdoo\Index\Controllers\DefaultController;
use Gdoo\Index\Services\AttachmentService;
use Illuminate\Support\Arr;

class TaskController extends DefaultController
{
    public $permission = ['drag', 'sort'];
    
    public function indexAction(Request $request)
    {
        $search = search_form([
            'project_id' => '',
            'tpl' => 'gantt',
            'referer' => '',
        ], [
            ['text','project_task.name','任务名称'],
            ['text','project_task.user_id','执行者'],
        ]);

        $query = $search['query'];

        if ($request->ajax() && $request->wantsJson()) {
            $tasks = $this->data($search);
            $_tasks = array_nest($tasks, 'name');
            $rows = [];
            foreach($_tasks as $_task) {
                $rows[] = $_task;
            }
            $json['data'] = $rows;
            return response()->json($json);
        }

        if ($request->ajax()) {
            $tasks = $this->data($search);
            return response()->json(['data' => $tasks]);
        }
        
        $project = Project::find($query['project_id']);

        // 生成权限
        $user_id = auth()->id();
        $permission = [
            'add_item' => $project['user_id'] == $user_id,
            'add_task' => $project['user_id'] == $user_id,
        ];

        // 返回页面
        $referer = session()->get('referer_'.$request->module().'_project_index');

        return $this->display([
            'project' => $project,
            'search' => $search,
            'query' => $query,
            'referer' => $referer,
            'permission' => $permission,

        ], 'index/'.$query['tpl']);
    }

    // 读取数据
    public function data($search)
    {
        $query = $search['query'];

        $user_id = auth()->id();

        $_items = Task::where('project_id', $query['project_id'])
        ->leftJoin('project', 'project.id', '=', 'project_task.project_id')
        ->where('parent_id', 0)
        ->orderBy('project_task.sort', 'asc')
        ->orderBy('project_task.id', 'asc')
        ->get(['project_task.*', 'project.user_id as project_user_id'])->toArray();

        $model = Task::with(['users' => function ($q) {
            $q->select(['user.id','user.name as user_name']);
        }]);

        $model->where('project_task.project_id', $query['project_id'])
        ->leftJoin('project', 'project.id', '=', 'project_task.project_id')
        ->leftJoin('user', 'user.id', '=', 'project_task.user_id')
        ->where('parent_id', '>', 0);
        
        foreach ($search['where'] as $where) {
            if ($where['active']) {
                $model->search($where);
            }
        }
        $_tasks = $model->select(['project_task.*','user.name as user_name', 'project.user_id as project_user_id'])
        ->orderBy('project_task.sort', 'asc')
        ->orderBy('project_task.id', 'asc')
        ->get()->toArray();

        foreach ($_items as $_item) {
            $project_user_id = 0;
            if ($_item['project_user_id'] == $user_id) {
                $project_user_id = 1;
            }
            
            $tasks[] = [
                'start_date' => '',
                'parent_id' => 0,
                'parent' => 0,
                'duration' => '',
                'loaded' => true,
                'expanded' => true,
                'id' => $_item['id'],
                'name' => $_item['name'],
                'type' => $_item['type'],
                'created_at' => '',
                'user_id' => '',
                'user_name' => '',
                'open' => true,
                'option_edit' => $project_user_id,
                'option_delete' => $project_user_id,
                'dhm' => '',
            ];
        }

        foreach ($_tasks as $_task) {
            $project_user_id = $task_user_id = 0;

            if ($_task['user_id'] == $user_id) {
                $task_user_id = 1;
            }

            // 显示保存按钮
            if ($_task['project_user_id'] == $user_id) {
                $task_user_id = $project_user_id = 1;
            }

            $_task['option_edit'] = $task_user_id;
            $_task['option_delete'] = $project_user_id;

            $_task['start_date'] = date('Y-m-d', $_task['start_at']);
            $_task['name'] = $_task['name'];
            $_task['parent'] = $_task['parent_id'];
            $_task['users'] = join(',', Arr::pluck($_task['users'], 'user_name'));
            $_task['open'] = true;
            $_task['loaded'] = true;
            $_task['expanded'] = true;
            $_task['created_at'] = format_datetime($_item['created_at']);

            if ($_task['start_at'] && $_task['end_at']) {
                $remain = remain_time($_task['start_at'], $_task['end_at'], '');
                $str = '';
                if ($remain->d) {
                    $str .= $remain->d.'天';
                }
                if ($remain->h) {
                    $str .= $remain->h.'小时';
                }
                if ($remain->i) {
                    $str .= $remain->i.'分钟';
                }
                $_task['duration_date'] = $str;
            }

            $_task['duration'] = ($_task['end_at'] - $_task['start_at']) / 86400;
            $_task['duration'] = $_task['duration'] > 0 ? $_task['duration'] : 1;

            $tasks[] = $_task;
        }
        return $tasks;
    }

    // 显示任务
    public function showAction(Request $request)
    {
        $search = search_form([
            'project_id' => ''
        ], [
            ['text','project.title','任务名称'],
            ['text','project.created_at','执行者'],
        ]);
        
        $project_id = $request->input('project_id');
        $project = Project::find($project_id);

        return $this->render([
            'project' => $project,
            'search' => $search,
        ]);
    }

    // 移动任务
    public function dragAction(Request $request)
    {
        $gets = $request->input();

        $task = Task::find($gets['id']);

        $task->start_at = strtotime($gets['start_date']);
        $task->end_at = strtotime($gets['end_date']);
        $task->progress = $gets['progress'];
        $task->save();
        
        return $this->json('恭喜您，任务移动成功。', true);
    }

    // 移动任务
    public function sortAction(Request $request)
    {
        $gets = $request->input();

        $task = Task::find($gets['id']);
        $task->parent_id = $gets['parent_id'];
        $task->save();

        $i = 0;
        foreach ($gets['sort'] as $id) {
            $task = Task::find($id);
            $task->sort = $i;
            $task->save();
            $i++;
        }
        return $this->json('恭喜您，任务移动成功。', true);
    }

    // 添加任务
    public function addAction(Request $request)
    {
        if ($request->method() == 'POST') {
            $gets = $request->input();

            if ($gets['name'] == '') {
                return $this->json('名称必须填写。');
            }

            if ($gets['start_at'] == '') {
                $gets['start_at'] = time();
            }

            $attachment = $gets['attachment'];
            $gets['attachment'] = join(',', (array)$attachment);

            $gets['start_at'] = strtotime($gets['start_at']);
            $gets['end_at'] = strtotime($gets['end_at']);

            $gets['user_id'] = $gets[$gets['type'].'_user_id'];

            $task = new Task();
            $task->fill($gets);
            $task->save();

            // 更新关系表
            $task->syncUsers($gets);

            // 附件发布
            AttachmentService::publish($attachment);

            if ($gets['is_item'] == '0') {
                $task = Task::find($task->id);
                $task->created_at = format_datetime($task->created_at);
                $task->user_name  = get_user($task->user_id, 'name', false);
                return $this->json($task, true);
            } else {
                return $this->json('恭喜你，添加任务成功。', true);
            }
        }

        $project_id = $request->input('project_id');
        $parent_id = $request->input('parent_id');
        $type = $request->input('type');

        $items = Task::where('project_id', $project_id)
        ->where('parent_id', 0)
        ->orderBy('id', 'desc')
        ->get();

        $tpl = $type == 'item' ? 'item/add' : 'add';
        return $this->render([
            'items' => $items,
            'project_id' => $project_id,
            'parent_id' => $parent_id,
            'type' => $type,
        ], $tpl);
    }

    // 编辑任务
    public function editAction(Request $request)
    {
        if ($request->method() == 'POST') {
            $gets = $request->input();

            if ($gets['name'] == '') {
                return $this->json('名称必须填写。');
            }

            $gets['progress'] = (int)$gets['progress'];

            $attachment = $gets['attachment'];
            $gets['attachment'] = join(',', (array)$attachment);
            
            $gets['start_at'] = strtotime($gets['start_at']);
            $gets['end_at'] = strtotime($gets['end_at']);

            $gets['user_id'] = $gets[$gets['type'].'_user_id'];
            
            $task = Task::find($gets['id']);
            $task->fill($gets);
            $task->save();

            // 更新关系表
            $task->syncUsers($gets);

            // 附件发布
            AttachmentService::publish($attachment);

            return $this->json('恭喜你，编辑任务成功。', true);
        }

        $id = $request->input('id');
        $type = $request->input('type');

        $task = Task::find($id);
        $task->users = $task->users()->pluck('user_id')->implode(',');

        $project = Project::find($task->project_id);

        $tasks = Task::where('project_task.parent_id', $task->id)
        ->leftJoin('user', 'user.id', '=', 'project_task.user_id')
        ->orderBy('project_task.id', 'desc')
        ->get(['project_task.*', 'user.avatar']);

        $items = Task::where('project_id', $task->project_id)
        ->where('parent_id', 0)
        ->orderBy('id', 'desc')
        ->get();
        
        $logs = Log::where('project_task_log.task_id', $id)
        ->leftJoin('user', 'user.id', '=', 'project_task_log.created_id')
        ->orderBy('project_task_log.id', 'desc')
        ->get(['project_task_log.*', 'user.avatar']);

        $auth_id = auth()->id();

        $permission = [
            'name' => 0,
            'status' => 0,
            'parent_id' => 0,
            'date' => 0,
            'user_id' => 0,
            'users' => 0,
            'remark' => 0,
            'attachment' => 0,
            'add-subtask' => 0,
            'add-comment' => 0,
        ];

        if ($project['user_id'] == $auth_id) {
            $permission = [
                'name' => 1,
                'status' => 1,
                'parent_id' => 1,
                'date' => 1,
                'user_id' => 1,
                'users' => 1,
                'remark' => 1,
                'attachment' => 1,
                'add-subtask' => 1,
                'add-comment' => 1,
            ];
        } elseif ($task['user_id'] == $auth_id) {
            $permission = [
                'name' => 1,
                'status' => 1,
                'parent_id' => 0,
                'date' => 0,
                'user_id' => 0,
                'users' => 1,
                'remark' => 1,
                'attachment' => 1,
                'add-subtask' => 0,
                'add-comment' => 1,
            ];
        } elseif (in_array($auth_id, (array)$task->users)) {
            $permission = [
                'name' => 0,
                'status' => 0,
                'parent_id' => 0,
                'date' => 0,
                'user_id' => 0,
                'users' => 0,
                'remark' => 0,
                'attachment' => 0,
                'add-subtask' => 0,
                'add-comment' => 1,
            ];
        }
        $tpl = $type == 'item' ? 'item/edit' : 'edit';
        return $this->render([
            'task' => $task,
            'logs' => $logs,
            'items' => $items,
            'tasks' => $tasks,
            'type' => $type,
            'permission' => $permission,
        ], $tpl);
    }

    // 删除任务
    public function deleteAction(Request $request)
    {
        if ($request->method() == 'POST') {
            $id = $request->input('id');
            $id = array_filter((array)$id);

            if (empty($id)) {
                return $this->json('请先选择数据。');
            }

            $tasks = Task::whereIn('id', $id)->get();

            foreach ($tasks as $task) {
                $logs = Log::where('task_id', $task->id)->get();
                foreach ($logs as $log) {
                    AttachmentService::remove($log->attachment);
                    $log->delete();
                }
                
                AttachmentService::remove($task->attachment);
                $task->users()->sync([]);
                $task->delete();
            }
            return $this->json('恭喜你，删除任务成功。', true);
        }
    }
}
