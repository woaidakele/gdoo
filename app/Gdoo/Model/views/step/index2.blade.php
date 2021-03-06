<script src="{{$asset_url}}/vendor/jquery.plumb.min.js" type="text/javascript"></script>

<script type="text/javascript">
var intervalTimer = null;
var nowStepId = null;
var normalStepSelector = ".wf-step:not(.wf-step-end)";
// var normalStepSelector = ".wf-step:not(.wf-step-start,.wf-step-end)";

// 判断连接器是否指向了自己
var isConnectToSelf = function(param) {
    return param.sourceId === param.targetId
},
isFromStartToEnd = function(param) {
    return param.sourceId === "step_1" && param.targetId === "step_0"
},
// 判断连接步骤是否重复
stepIsRepeat = function(param){
    // 获取同一scope下的链接器，判断当前链接是否有重复
    var cnts = jsPlumb.getConnections(param.scope);
    if(cnts.length > 0) {
        for(var i = 0, len = cnts.length; i < len; i++) {
            // 如果有链接器 sourceId 及 targetId 都相等，那大概也许可能就相等了。
            if(param.sourceId === cnts[i].sourceId && param.targetId === cnts[i].targetId) {
                return true;
            }
        }
    }
    return false;
};

// 连接前的事件
var beforeDropHandler = function(param) {

    if(isConnectToSelf(param)) {
        toastrError('连接点不能是自己。');
        return false;
    }
    // 连接步骤是否重复
    if(stepIsRepeat(param)) {
        toastrError('连接点已经存在。');
        return false;
    }
    // 连接步骤是结束
    if(isFromStartToEnd(param)) {
        return false;
    }
    return true;
};

$(document).ready(function()
{
    // 获取步骤图
    workStep.init();

    $('#graph').contextmenu({'target':'#graph-menu'});

    // 阻止非空白处的右键事件，不出现菜单
    //$container.on("contextmenu", "*", function(evt) {
        //evt.stopPropagation();
    //});

    // 点击或双击事件,这里进行了一个单击事件延迟，因为同时绑定了双击事件
    $("#rows").on('click', 'div', function() {
        clearTimeout(intervalTimer);
        intervalTimer = setTimeout(function() {
            workStep.click();
        },300);

    }).on('dblclick', 'div', function(e) {
        nowStepId = $(e.target).attr('step_id');
        clearTimeout(intervalTimer);
        workStep.dblClick('base');
    });
});

var workStep = {

    init:function() {

        jsPlumb.reset();

        jsPlumb.bind("beforeDrop", beforeDropHandler);

        $.post("{{url('index2')}}",{bill_id:'{{$bill_id}}'},function(res)
        {
            $('#rows').empty();
            var nLastStepId = 0;

            if(res.status == true) {
                jsPlumb.importDefaults({
                    DragOptions:{cursor:'pointer'},
                    ConnectionOverlays:[
                        ["Arrow",{location:1,id:"arrow",width:10,length:8}]
                    ],
                    Anchor:'Continuous',
                    Endpoint :"Blank",
                    EndpointStyle:{radius:3,strokeStyle:'#1e8151'},
                    HoverPaintStyle:{radius:3,strokeStyle:'#999'},
                    ConnectorZIndex:5,
                    HoverPaintStyle:workStep.connectorHoverStyle
                });

                if(!$.support.leadingWhitespace) {
                    // ie9以下，用VML画图
                    jsPlumb.setRenderMode(jsPlumb.VML);
                } else {
                    // 其他浏览器用SVG
                    jsPlumb.setRenderMode(jsPlumb.SVG);
                }

                // 第一次循环，生成页面元素div
                var left = 20;
                var top = 20;

                var rows = res.data;
                var setd = {};

                $.each(rows, function(id, row) {
                    var title = '<span class="ep">' + (row.option == 1 ? '审核' : '知会') + '</span>&nbsp;' + row.name;
                    if (row.type == 'start') {
                        title = '<span class="ep"><i class="icon icon-play"></i></span>&nbsp;' + row.name;
                    } else if(row.type == 'end') {
                        title = '<span class="ep"><i class="icon icon-stop"></i></span>&nbsp;' + row.name;
                    }

                    var css = 'wf-step';
                    if (row.type == 'start') {
                        css = 'wf-step wf-step-start';
                    } else if(row.type == 'end') {
                        css = 'wf-step wf-step-end';
                    }

                    var nodeDiv = document.createElement('div');
                    var $node = $(nodeDiv);

                    $node.attr("id", "step_" + row.id)
                    .attr("join", row.join)
                    .attr("step_id", row.id)
                    .css({"left":row.left+'px',"top":row.top+'px',"cursor":"move"})
                    .addClass(css)
                    .html(title);

                    /*
                    $(normalStepSelector).on('mousedown', function(e) {

                        //if(row.number > 0) {
                            nowStepId = $(e.target).attr('step_id');
                            // $(this).contextmenu({'target':'#step-menu'});
                        //}
                        //e.stopPropagation();
                    });
                    */

                    $("#rows").append($node);
                    
                    // 索引变量
                    nLastStepId++;

                });

                // 绑定右键操作菜单
                $(normalStepSelector).on('mousedown', function(e) {
                    nowStepId = $(e.target).attr('step_id');
                    $(this).contextmenu({'target':'#step-menu'});
                    // e.stopPropagation();
                });

                // 使之可拖动
                jsPlumb.draggable($(".wf-step"));
                
                $(".ep").each(function(i,e) {
                    var p = $(e).parent();
                    jsPlumb.makeSource($(e), {
                        parent:p,
                        anchor:"Continuous",
                        endpoint:"Blank",
                        paintStyle:{
                            strokeStyle:"#3399cc",
                            fillStyle:"transparent",
                            radius:2,
                            lineWidth:2
                        },
                        // stub 线条弯曲角度 gap 距离节点位置 cornerRadius 线条圆角
                        connector:["Flowchart",{stub:[10, 30], gap:0, cornerRadius:3, alwaysRespectStubs:true}],
                        connectorStyle:workStep.connectorPaintStyle,
                        hoverPaintStyle:workStep.endpointHoverStyle,
                        connectorHoverStyle:workStep.connectorHoverStyle,
                        dragOptions:{},
                        maxConnections:-1
                    });
                });

                // 绑定删除确认操作
                jsPlumb.bind("click", function(e) {
                    jsPlumb.detach(e);
                });

                // 连接关联的步骤
                $('.wf-step').each(function(i) {
                    var id = $(this).attr('id');
                    var join = $(this).attr('join') || '';
                    var joinArray = join.split(",");
                    $.each(joinArray,function(j, n) {
                        if(n > 0) {
                            jsPlumb.connect({source:id, target:"step_"+n});
                        }
                    })
                });

                jsPlumb.makeTarget($('.wf-step'), {
                    dropOptions: {hoverClass:"dragHover", activeClass:"active"},
                    anchor: "Continuous",
                    maxConnections: 5
                });
            }
        },'json');
    },
    connectorPaintStyle:{
        lineWidth:2,
        strokeStyle:"#3399cc",
        joinstyle:"round",
        outlineColor:"white",
        dashstyle:"0",
        outlineWidth:2
    },
    connectorHoverStyle:{
        lineWidth:2,
        strokeStyle:"#999999",
        outlineWidth:2,
        dashstyle:"4 1",
        outlineColor:"white"
    },
    endpointHoverStyle:{
        strokeStyle:"#216477"
    },
    // 清空所有连接
    clear:function() {
        jsPlumb.detachEveryConnection();
        jsPlumb.deleteEveryEndpoint();
    },
    // 单击事件
    click:function() {
        if (nowStepId > 0) {
            $('#tree').hide();
            $.post('{{url("show")}}',{id:nowStepId, bill_id:'{{$bill_id}}'}, function(res) {
                $('#tree').html(res.data).show();
            })
        }
    },
    // 删除步骤
    deleted:function() {
        $.messager.confirm('操作警告','确认要删除该步骤吗?',function(btn) {
            if (btn) {
                $.post('{{url("delete")}}',{id:nowStepId, bill_id:'{{$bill_id}}'}, function(res) {
                    if (res.status) {
                        workStep.init();
                        toastrSuccess('操作成功');
                    }
                },'json');
            }
            
        });
    },
    // 克隆步骤
    clone:function() {
        $.messager.confirm('操作警告','确认要克隆该步骤吗?',function(btn) {
            if (btn) {
                $.post('{{url("add")}}',{id:nowStepId,bill_id:'{{$bill_id}}'},function(data) {
                    if(data.status) {
                        workStep.init();
                        toastrSuccess('操作成功');
                    }
                },'json');
            }
        });
    },
    dblClick:function(tab) {
        if (nowStepId > 0) {
            if(tab == 'base') {
                $.dialog({
                    title:'基本设置',
                    url:'{{url("create")}}?bill_id={{$bill_id}}&id='+nowStepId+'&tab='+tab,
                    dialogClass: 'modal-lg',
                    buttons:[{
                        class: 'btn-info',
                        text:'<i class="fa fa-check-circle"></i> 提交',
                        click: function() {
                            var formData = $('#mystep').serialize();
                            $.post('{{url("create")}}', formData, function(res) {
                                if (res.status) {
                                    workStep.init();
                                    toastrSuccess('保存当前视图成功');
                                }
                            },'json');
                            $(this).dialog("close");
                        }
                    },{
                        text:'<i class="fa fa-times"></i> 取消',
                        click: function() {
                            $(this).dialog("close");
                        }
                    }]
                });
            }
            if (tab == 'condition') {
                $.dialog({
                    title:'条件设置',
                    url:'{{url("condition")}}?bill_id={{$bill_id}}&id='+nowStepId+'&tab='+tab,
                    dialogClass: 'modal-lg',
                    buttons:[{
                        class: 'btn-info',
                        text:'<i class="fa fa-check-circle"></i> 提交',
                        click: function() {
                            var formData = $('#conditionform').serialize();
                            $.post('{{url("condition")}}', formData, function(res) {
                                if (res.status) {
                                    workStep.init();
                                    toastrSuccess('保存当前视图成功');
                                }
                            },'json');
                            $(this).dialog("close");
                        }
                    },{
                        text:'<i class="fa fa-times"></i> 取消',
                        click: function() {
                            $(this).dialog("close");
                        }
                    }]
                });
            }
        }
    },
    add:function() {
        $.post('{{url("add")}}',{bill_id:'{{$bill_id}}'},function(res) {
            if (res.status) {
                workStep.init();
                toastrSuccess('操作成功');
            }
        },'json');
    },
    save:function() {
        var join = [],position = [];
        var rows = jsPlumb.getConnections();

        if(rows.length == 0) {
            toastrError('请先建立连接');
            return;
        }

        for (var i = 0; i < rows.length; i++) {
            var sourceId = rows[i].sourceId.substring(5);
            var targetId = rows[i].targetId.substring(5);
            join[i] = {id:sourceId,target:targetId};
        }

        $('#rows div').each(function(i) {
            if(this.id.substring(0,5) == 'step_') {
                var stepId = this.id.substring(5);
                var left = $(this).css('left');
                var top = $(this).css('top');
                position[i] = {'id':stepId,'posX':left,'posY':top};
            }
        });

        $.post('{{url("save")}}', {join:join,position:position,bill_id:'{{$bill_id}}'},function(result) {
            if(result.status) {
                toastrSuccess('保存当前视图成功');
            }
        },'json');
    }
}
</script>

<style>
html { overflow: hidden; }
.form-panel-body {
    overflow: hidden;
}
.graph-box {
    overflow: auto;
    width: 100vw;
    height: calc(100vh - 48px);
}
.content-body {
    margin: 0;
}
.font-thin {
    padding: 10px;
    padding-bottom: 0;
    font-size: 14px;
}
</style>

<div class="form-panel">

    <div class="form-panel-header">
        <div class="pull-right m-r-sm">
        <a class="btn btn-sm btn-info" onclick="workStep.add();" href="javascript:;">新建步骤</a>
        <a class="btn btn-sm btn-default" onclick="workStep.init();" href="javascript:;">重新加载</a>
        <a href="javascript:;" onclick="workStep.clear();" class="btn btn-sm btn-default"><i class="fa fa-ban"></i> 清空连接</a>
        <a href="javascript:;" onclick="workStep.save();" class="btn btn-sm btn-info"><i class="fa fa-random"></i> 保存连接和视图</a>
        <a class="btn btn-sm btn-default" data-toggle="closetab" data-id="flow_step_index2"><i class="fa fa-sign-out"></i> 退出</a>
        </div>
        <div class="font-thin">
            <i class="fa fa-file-text-o"></i> {{$bill['name']}}流程
        </div>
    </div>

    <div class="form-panel-body">
        <div class="wrapper-xs graph-box">
                <div class="graph" id="graph" data-toggle="context" data-target="#graph-menu">
                    <div id="rows"></div>
                </div>
                <div id="graph-menu">
                    <ul class="dropdown-menu" role="menu">
                        <li role="presentation"><a role="menuitem" onclick="workStep.add();" href="javascript:;">新建步骤</a></li>
                        <li role="presentation" class="divider"></li>
                        <li role="presentation"><a role="menuitem" onclick="workStep.save();" href="javascript:;">保存视图</a></li>
                        <li role="presentation"><a role="menuitem" onclick="workStep.init();" href="javascript:;">重新加载</a></li>
                    </ul>
              </div>
              <div id="step-menu">
                    <ul class="dropdown-menu" role="menu">
                        <li role="presentation"><a role="menuitem" onclick="workStep.dblClick('base');" href="javascript:;">基本设置</a></li>
                        <!--
                        <li role="presentation"><a role="menuitem" onclick="workStep.dblClick('handle');" href="javascript:;">经办权限</a></li>
                        <li role="presentation"><a role="menuitem" onclick="workStep.dblClick('field');" href="javascript:;">表单字段</a></li>
                        -->
                        <li role="presentation"><a role="menuitem" onclick="workStep.dblClick('condition');" href="javascript:;">条件设置</a></li>
                        <li role="presentation" class="divider"></li>
                        <li role="presentation"><a role="menuitem" onclick="workStep.clone();" href="javascript:;">克隆该步骤</a></li>
                        <li role="presentation"><a role="menuitem" onclick="workStep.deleted();" href="javascript:;">删除该步骤</a></li>
                    </ul>
              </div>
        </div>
    </div>
    <div class="clearfix"></div>
</div>

</div>
</div>
