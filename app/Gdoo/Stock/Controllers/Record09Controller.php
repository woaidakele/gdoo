<?php namespace Gdoo\Stock\Controllers;

use DB;
use Request;
use Validator;

use Gdoo\User\Models\User;
use Gdoo\Stock\Models\Record09;

use Gdoo\Model\Grid;
use Gdoo\Model\Form;

use Gdoo\Index\Controllers\WorkflowController;

class Record09Controller extends WorkflowController
{
    public $permission = ['dialog'];

    // 列表
    public function indexAction()
    {
        $header = Grid::header([
            'code' => 'stock_record09',
            'referer' => 1,
            'search' => ['by' => ''],
        ]);

        $cols = $header['cols'];

        $cols['actions']['options'] = [[
            'name' => '显示',
            'action'  => 'show',
            'display' => $this->access['show'],
        ]];

        $search = $header['search_form'];
        $query = $search['query'];

        if (Request::method() == 'POST') {
            $model = DB::table($header['table'])->setBy($header);
            foreach ($header['join'] as $join) {
                $model->leftJoin($join[0], $join[1], $join[2], $join[3]);
            }
            $model->orderBy($header['sort'], $header['order']);

            foreach ($search['where'] as $where) {
                if ($where['active']) {
                    $model->search($where);
                }
            }
            
            $model->select($header['select']);
            $rows = $model->paginate($query['limit'])->appends($query);
            $items = Grid::dataFilters($rows, $header);
            return $items->toJson();
        }

        $header['buttons'] = [
            //['name' => '删除', 'icon' => 'fa-remove', 'action' => 'delete', 'display' => $this->access['delete']],
            ['name' => '导出', 'icon' => 'fa-share', 'action' => 'export', 'display' => 1],
        ];

        $header['cols'] = $cols;
        $header['tabs'] = Record09::$tabs;
        $header['bys'] = Record09::$bys;
        $header['js'] = Grid::js($header);

        return $this->display([
            'header' => $header,
        ]);
    }

    // 新建
    public function createAction($action = 'edit')
    {
        $id = (int) Request::get('id');
        $header['action'] = $action;
        $header['code'] = 'stock_record09';
        $header['id'] = $id;
        
        $form = Form::make($header);
        $tpl = $action == 'print' ? 'print' : 'create';
        return $this->display([
            'form' => $form,
        ], $tpl);
    }

    // 编辑
    public function editAction()
    {
        return $this->createAction();
    }

    // 审核
    public function auditAction()
    {
        return $this->createAction();
    }

    // 显示
    public function showAction()
    {
        return $this->createAction('show');
    }

    // 打印
    public function printAction()
    {
        $id = Request::get('id');
        $template_id = Request::get('template_id');

        $this->layout = 'layouts.print3';
        $master = DB::table('stock_record09 as m')->where('m.id', $id)
        ->leftJoin('stock_type as st', 'st.id', '=', 'm.type_id')
        ->leftJoin('department', 'department.id', '=', 'm.department_id')
        ->leftJoin('warehouse', 'warehouse.id', '=', 'm.warehouse_id')
        ->selectRaw('m.*, st.name as type_name, warehouse.name as warehouse_name, department.name as department_name')
        ->first();

        $rows = DB::table('stock_record09_data as d')
        ->leftJoin('stock_record09 as m', 'm.id', '=', 'd.record09_id')
        ->leftJoin('product as p', 'p.id', '=', 'd.product_id')
        ->leftJoin('product_unit as pu', 'pu.id', '=', 'p.unit_id')
        ->leftJoin('stock_type as st', 'st.id', '=', 'm.type_id')
        ->where('m.id', $id)
        ->selectRaw('
            d.*,
            p.name as product_name,
            p.code as product_code,
            p.spec as product_spec,
            st.name as type_name,
            pu.name as product_unit
        ')
        ->orderBy('p.code', 'asc')
        ->get();

        $form = [
            'template' => DB::table('model_template')->where('id', $template_id)->first()
        ];

        $tpl = $this->display([
            'master' => $master,
            'rows' => $rows,
            'form' => $form,
        ], 'print/'.$template_id);
        return $tpl;
    }

    // 删除
    public function deleteAction()
    {
        if (Request::method() == 'POST') {
            $ids = Request::get('id');
            return Form::remove(['code' => 'stock_record09', 'ids' => $ids]);
        }
    }
}
