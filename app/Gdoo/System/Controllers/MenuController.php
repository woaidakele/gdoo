<?php namespace Gdoo\System\Controllers;

use DB;
use Request;
use Validator;

use Gdoo\System\Models\Menu;

use Gdoo\Model\Grid;

use Gdoo\Index\Controllers\DefaultController;

class MenuController extends DefaultController
{
    // 菜单列表
    public function indexAction()
    {
        $header = [
            'master_name' => '模型',
            'simple_search_form' => 1,
            'table' => 'menu',
            'master_table' => 'menu',
            'create_btn' => 1,
        ];

        $search = search_form([
            'advanced' => '',
        ], [
            ['form_type' => 'text', 'name' => '名称', 'field' => 'menu.name', 'value' => '', 'options' => []],
        ], 'model');

        $header['cols'] = [
            'checkbox' => [
                'width' => 40,
                'suppressSizeToFit' => true,
                'cellClass' => 'text-center',
                'suppressMenu' => true,
                'sortable' => false,
                'editable' => false,
                'resizable' => false,
                'filter' => false,
                'checkboxSelection' => true,
                'headerCheckboxSelection' => true,
            ],
            'sequence_sn' => [
                'width' => 60,
                'headerName' => '序号',
                'suppressSizeToFit' => true,
                'cellClass' => 'text-center',
                'suppressMenu' => true,
                'sortable' => false,
                'resizable' => false,
                'editable' => false,
                'type' => 'sn',
                'filter' => false,
            ],
            'text' => [
                'field' => 'text',
                'headerName' => '名称',
                'sortable' => true,
                'suppressMenu' => true,
                'cellClass' => 'text-left',
                'form_type' => 'text',
                'width' => 100,
            ],
            'url' => [
                'field' => 'url',
                'headerName' => 'URL',
                'sortable' => true,
                'suppressMenu' => true,
                'cellClass' => 'text-left',
                'form_type' => 'text',
                'width' => 0,
            ],
            'access' => [
                'field' => 'access',
                'headerName' => '认证',
                'sortable' => true,
                'suppressMenu' => true,
                'cellClass' => 'text-center',
                'form_type' => 'text',
                'width' => 80,
            ],
            'sort' => [
                'field' => 'sort',
                'headerName' => '排序',
                'sortable' => true,
                'suppressMenu' => true,
                'cellClass' => 'text-center',
                'form_type' => 'text',
                'width' => 80,
            ],
            'status_name' => [
                'field' => 'status_name',
                'headerName' => '状态',
                'sortable' => true,
                'suppressMenu' => true,
                'cellClass' => 'text-center',
                'width' => 120,
            ],
            'id' => [
                'field' => 'id',
                'headerName' => 'ID',
                'sortable' => true,
                'suppressMenu' => true,
                'cellClass' => 'text-center',
                'form_type' => 'text',
                'width' => 40,
            ],
            'actions' => [
                'headerName' => '',
                'cellRenderer' => 'actionCellRenderer',
                'options' => [[
                    'name' => '编辑',
                    'action' => 'edit',
                    'display' => $this->access['edit'],
                ]],
                'width' => 100,
                'cellClass' => 'text-center',
                'suppressSizeToFit' => true,
                'suppressMenu' => true,
                'sortable' => false,
                'editable' => false,
                'resizable' => false,
                'filter' => false,
            ],
        ];
        $query = $search['query'];

        if (Request::method() == 'POST') {
            $model = Menu::setBy($header);
            foreach ($search['where'] as $where) {
                if ($where['active']) {
                    $model->search($where);
                }
            }
            $model->orderBy('lft', 'asc');
            $model->selectRaw('*, id as master_id');
            $rows = $model->paginate($query['limit'])->appends($query);

            $rows->transform(function($row) {
                $row['access'] = $row['access'] == 1 ? '是' : '否';
                if ($row['status'] == 1) {
                    $row['status_name'] = '启用';
                } else {
                    $row['status_name'] = '禁用';
                }
                return $row;
            });

            $items = $rows->items();
            array_nest($items);
            $rows->items($items);
           
            return $rows;
        }

        $header['buttons'] = [
            ['name' => '删除', 'icon' => 'fa-remove', 'action' => 'delete', 'display' => $this->access['delete']],
            ['name' => '导出', 'icon' => 'fa-share', 'action' => 'export', 'display' => 1],
        ];

        $header['search_form'] = $search;
        $header['js'] = Grid::js($header);

        // 配置权限
        return $this->display([
            'header' => $header,
        ]);
        /*
        if (Request::method() == 'POST') {
            $sorts = Request::get('sort');
            foreach ($sorts as $id => $sort) {
                DB::table('menu')->where('id', $id)->update(['sort' => $sort]);
            }
            tree_rebuild('menu');
            return $this->success('index', '恭喜你，操作成功。');
        }

        $search = search_form([
            'referer'  => 1,
        ], []);
        
        $rows = DB::table('menu')
        ->orderBy('lft', 'asc')
        ->get();
        */

        /*
        $rows = DB::table('dict')->get();

        foreach ($rows as $key => $row) {

            $values = json_decode($row['value'], true);

            //$data['parent_id'] = $row['id'];

            $id = DB::table('option')->insertGetId([
                'parent_id' => 0,
                'name'  => $row['name'],
                'value' => $row['key'],
            ]);

            $data = [];

            $data['parent_id'] = $id;

            if($values) {

            foreach ($values as $k => $v) {

                $data['sort']  = $k;
                $data['value'] = ''.$v['id'].'';
                $data['name']  = ''.$v['name'].'';
                DB::table('option')->insert($data);
            }
            }
        }

        print_r($rows);
        exit;
        */

        //$rows = array_nest($rows);
        /*
        return $this->display([
            'rows' => $rows,
        ]);
        */
    }

    // 新建菜单
    public function createAction()
    {
        $id = (int)Request::get('id');

        if (Request::method() == 'POST') {
            $gets = Request::all();
            $rules = [
                'name' => 'required',
            ];
            $v = Validator::make($gets, $rules);
            if ($v->fails()) {
                return $this->back()->withErrors($v)->withInput();
            }
            if ($gets['id']) {
                DB::table('menu')->where('id', $gets['id'])->update($gets);
            } else {
                DB::table('menu')->insert($gets);
            }
            tree_rebuild('menu');
            return $this->json('恭喜你，操作成功。', true);
        }

        $row = DB::table('menu')->where('id', $id)->first();
        $parents = DB::table('menu')->orderBy('lft', 'asc')->get();
        $parents = array_nest($parents);

        return $this->render([
            'row' => $row,
            'parents' => $parents,
        ], 'create');
    }

    public function editAction() {
        return $this->createAction();
    }

    public function storeAction()
    {
        return $this->editAction();
    }

    // 删除菜单
    public function deleteAction()
    {
        if (Request::method() == 'POST') {
            $id = Request::get('id');
            $count = DB::table('menu')->whereIn('parent_id', $id)->count();
            if ($count) {
                return $this->json('存在子菜单无法删除。');
            }
            DB::table('menu')->whereIn('id', $id)->delete();
            return $this->json('删除成功。', true);
        }
    }
}
