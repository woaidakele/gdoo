{{$header["js"]}}

<div class="panel no-border" id="{{$header['table']}}-controller">
    @include('headers')
    <div class='list-jqgrid'>
        <div id="{{$header['table']}}-grid" style="width:100%;" class="ag-theme-balham"></div>
    </div>
</div>
<script>
    (function ($) {
        var table = '{{$header["table"]}}';
        var config = gdoo.grids[table];
        var action = config.action;
        var search = config.search;

        // 自定义搜索方法
        search.searchInit = function (e) {
            var self = this;
        }
        
        var options = new agGridOptions();
        var gridDiv = document.querySelector("#{{$header['table']}}-grid");
        gridDiv.style.height = getPanelHeight(48);

        options.remoteDataUrl = '{{url()}}';
        options.remoteParams = search.advanced.query;
        options.columnDefs = config.cols;
        options.onRowDoubleClicked = function (params) {
            if (params.node.rowPinned) {
                return;
            }
            if (params.data == undefined) {
                return;
            }
            if (params.data.id > 0) {
                action.edit(params.data);
            }
        };

        new agGrid.Grid(gridDiv, options);

        // 读取数据
        options.remoteData({page: 1});

        // 绑定自定义事件
        var $gridDiv = $(gridDiv);
        $gridDiv.on('click', '[data-toggle="event"]', function () {
            var data = $(this).data();
            if (data.master_id > 0) {
                action[data.action](data);
            }
        });
        config.grid = options;
    })(jQuery);

</script>
@include('footers')