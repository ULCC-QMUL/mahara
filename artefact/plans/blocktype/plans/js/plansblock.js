function rewriteTaskTitles(blockid, planid) {
  jQuery('tasklist_' + blockid + '_plan' + planid + ' a.task-title').each(function() {
      jQuery(this).off();
      jQuery(this).on('click', function(e) {
          e.preventDefault();
          var description = jQuery(this).parent().find('div.task-desc');
          description.toggleClass('hidden');
      });
  });
}
function TaskPager(blockid, planid) {
    var self = this;
    paginatorProxy.addObserver(self);
    jQuery(self).on('pagechanged', rewriteTaskTitles.bind(null, blockid, planid));
}

var taskPagers = [];

function initNewPlansBlock(blockid) {
    var planslist = $(".bt-plans .pagination-wrapper");
    jQuery.each(
        planslist,
        function(key, value){
          var nodeid  = getNodeAttribute(value, 'id');
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
