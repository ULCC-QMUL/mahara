function rewriteTaskTitles(blockid, planid) {
    forEach(
        getElementsByTagAndClassName('a', 'task-title', 'tasklist_' + blockid + '_plan' + planid),
        function(element) {
            disconnectAll(element);
            connect(element, 'onclick', function(e) {
                e.stop();
                var description = getFirstElementByTagAndClassName('div', 'task-desc', element.parentNode);
                toggleElementClass('hidden', description);
            });
        }
    );
}
function TaskPager(blockid, planid) {
    var self = this;
    paginatorProxy.addObserver(self);
    connect(self, 'pagechanged', partial(rewriteTaskTitles, blockid, planid));
}

var taskPagers = [];

function initNewPlansBlock(blockid) {
    var planslist = $$(".bt-plans .pagination-wrapper");
    forEach(
        planslist,
        function(el) {
            var nodeid  = getNodeAttribute(el, 'id');
            var frompos = nodeid.indexOf('_') + 5;
            var topos   = nodeid.lastIndexOf('_');
            var planid  = nodeid.substring(frompos, topos);

            var data = [];
            data['block']  = blockid;
            data['planid'] = planid;
            new Paginator(nodeid, 'tasklist_' + blockid + '_plan' + planid, null, 'artefact/plans/viewtasks.json.php', data);
            taskPagers.push(new TaskPager(blockid, planid));
            rewriteTaskTitles(blockid, planid);
        }
    );
}
