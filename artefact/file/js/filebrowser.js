function FileBrowser(idprefix, folderid, config, globalconfig) {

    var self = this;
    this.id = idprefix;
    this.folderid = folderid;
    this.config = config;
    this.config.wwwroot = globalconfig.wwwroot;
    this.config.theme = globalconfig.theme;
    this.nextupload = 0;

    this.init = function () {
        self.form = $(self.formname);
        if (!self.form) {
            alert('eek');
        }
        self.foldername = $(self.id + '_foldername').value;
        if (self.config.select) {
            self.select_init();
        }
        if (self.config.upload) {
            self.upload_init();
        }
        self.browse_init();
        if (self.config.edit) {
            self.edit_init();
        }
    }

    this.upload_init = function () {
        if ($(self.id + '_notice')) {
            setNodeAttribute(self.id + '_userfile', 'disabled', true);
        }
        if (!$(self.id + '_upload')) {
            appendChildNodes(self.form, INPUT({
                'type': 'hidden',
                'name': self.id + '_upload',
                'id' : self.id + '_upload',
                'value':0
            }));
        }
        self.upload_connectbuttons();
    }

    this.upload_connectbuttons = function () {
        if ($(self.id + '_notice')) {
            connect(self.id + '_notice', 'onclick', function (e) {
                // error class is too general?
                forEach(getElementsByTagAndClassName('div', 'error', self.id + '_upload_messages'), removeElement);
                if (this.checked) {
                    $(self.id + '_userfile').disabled = false; // setNodeAttribute to false doesn't work here.
                } else {
                    setNodeAttribute(self.id + '_userfile', 'disabled', true);
                }
            });
        }
        connect(self.id + '_userfile', 'onchange', self.upload_submit);
    }

    this.upload_validate = function () {
        if ($(self.id + '_notice') && !$(self.id + '_notice').checked) {
            appendChildNodes(self.id+'_upload_messages', DIV({'class':'error'}, get_string('youmustagreetothecopyrightnotice')));
            return false;
        }
        return !isEmpty($(self.id + '_userfile').value);
    }

    this.upload_presubmit = function (e) {
        // Display upload status
        self.nextupload++;
        var localname = basename($(self.id + '_userfile').value);
        var message = makeMessage(DIV(null,
            IMG({'src':get_themeurl('images/loading.gif')}), ' ',
            get_string('uploadingfiletofolder',localname,self.foldername)
        ), 'info');
        setNodeAttribute(message, 'id', 'uploadstatusline' + self.nextupload);
        appendChildNodes(self.id + '_upload_messages', message);
        $(self.id+'_uploadnumber').value = self.nextupload;
        return true;
    }

    this.upload_submit = function (e) {
        e.stop();
        if (!self.upload_validate()) {
            return false;
        }

        self.upload_presubmit();
        $(self.id + '_upload').value = 1;
        signal(self.form, 'onsubmit');
        self.form.submit();
        // $(self.id + '_userfile').value = ''; // Won't work in IE
        replaceChildNodes(self.id + '_userfile_container', INPUT({
            'type':'file',
            'class':'file',
            'id':self.id+'_userfile',
            'name':'userfile',
            'size':40
        }));
        connect(self.id + '_userfile', 'onchange', self.upload_submit);
        $(self.id + '_upload').value = 0;
        return false;
    }

    this.fileexists = function (filename, id) {
        for (var i in self.filedata) {
            if (self.filedata[i].title == filename && (!id || i != id)) {
                return true;
            }
        }
        return false;
    }

    this.createfolder_submit = function (e) {
        var message;
        var name = $(self.id + '_createfolder_name');
        if (!name) {
            message = get_string('foldernamerequired');
        }
        else {
            name = name.value;
            if (name == '') {
                message = get_string('foldernamerequired');
            }
            else if (self.fileexists(name)) {
                message = get_string('filewithnameexists', name);
            }
        }
        if (message) {
            e.stop();
            replaceChildNodes(self.id + '_createfolder_messages', makeMessage(message, 'error'));
            return false;
        }
    }

    this.edit_submit = function (e) {
        var message;
        var name = $(self.id + '_edit_title');
        if (!name) {
            message = get_string('namefieldisrequired');
        }
        else {
            name = name.value;
            if (name == '') {
                message = get_string('namefieldisrequired');
            }
            else if (self.fileexists(name, this.name.replace(/.*_update\[(\d+)\]$/, '$1'))) {
                message = get_string('filewithnameexists', name);
            }
        }
        if (message) {
            e.stop();
            replaceChildNodes(self.id + '_edit_messages', makeMessage(message, 'error'));
            return false;
        }
    }

    this.upload_success = function (data) {
        if (data.problem) {
            var image = 'images/icon_problem.gif';
        }
        else if (!data.error) {
            var image = 'images/success.gif';
        }
        else {
            var image = 'images/failure.gif';
        }

        quotaUpdate(data.quotaused, data.quota);
        var newmessage = makeMessage(DIV(null,IMG({'src':get_themeurl(image)}), ' ', data.message));
        replaceChildNodes($('uploadstatusline'+data.uploadnumber), newmessage);
    }

    this.edit_form = function (e) {
        e.stop();
        // In IE, this.value is set to the button text
        var id = getNodeAttribute(this, 'name').replace(/.*_edit\[(\d+)\]$/, '$1');
        addElementClass(self.id + '_edit_row', 'hidden');
        $(self.id + '_edit_heading').innerHTML = self.filedata[id].artefacttype == 'folder' ? get_string('editfolder') : get_string('editfile');
        $(self.id + '_edit_title').value = self.filedata[id].title;
        $(self.id + '_edit_description').value = self.filedata[id].description;
        $(self.id + '_edit_tags').value = self.filedata[id].tags.join(', ');
        replaceChildNodes($(self.id + '_edit_messages'));
        forEach(getElementsByTagAndClassName('input', 'permission', self.id + '_edit_row'), function (elem) {
            var perm = getNodeAttribute(elem, 'name').split(':');
            if (self.filedata[id].permissions[perm[1]] && self.filedata[id].permissions[perm[1]][perm[2]] == 1) {
                elem.checked = true;
            }
        });
        // $(self.id + '_edit_artefact').value = id; // Changes button text in IE
        setNodeAttribute(self.id + '_edit_artefact', 'name', self.id + '_update[' + id + ']');
        var edit_row = removeElement(self.id + '_edit_row');
        var this_row = getFirstParentByTagAndClassName(this, 'tr');
        insertSiblingNodesAfter(this_row, edit_row);
        removeElementClass(edit_row, 'hidden');
        return false;
    }

    this.edit_init = function () { augment_tags_control(self.id + '_edit_tags'); }

    this.browse_submit = function (e) {
        signal(self.form, 'onsubmit');
        self.form.submit();
        e.stop();
        $(self.id + '_changefolder').value = '';
        return false;
    }

    this.browse_init = function () {
        if (self.config.edit) {
            forEach(getElementsByTagAndClassName('button', null, 'filelist'), function (elem) {
                var name = getNodeAttribute(elem, 'name').match(new RegExp('^' + self.id + "_([a-z]+)\\[(\\d+)\\]$"));
                if (name[1] == 'edit') {
                    connect(elem, 'onclick', self.edit_form);
                }
                else if (name[1] == 'delete') {
                    var id = name[2];
                    if (self.filedata[id].attachcount > 0) {
                        connect(elem, 'onclick', function (e) {
                            if (!confirm(get_string('detachfilewarning', self.filedata[id].attachcount))) {
                                e.stop();
                                return false;
                            }
                        });
                    }
                }
            });
            connect(self.id + '_edit_cancel', 'onclick', function (e) {
                e.stop();
                addElementClass(self.id + '_edit_row', 'hidden');
                return false;
            });
            connect(self.id + '_edit_artefact', 'onclick', self.edit_submit);

            // IE doesn't like it when Mochikit has droppables registered without elements attached.
            forEach(Draggables.drags, function (drag) { drag.destroy(); });
            forEach(Droppables.drops, function (drop) { drop.destroy(); });

            forEach(getElementsByTagAndClassName('div', 'icon-drag', 'filelist'), function (elem) {
                self.make_icon_draggable(elem);
            });
            forEach(getElementsByTagAndClassName('tr', 'folder', 'filelist'), self.make_row_droppable);
        }
        forEach(getElementsByTagAndClassName('a', 'changefolder', self.id + '_upload_browse'), function (elem) {
            connect(elem, 'onclick', function (e) {
                var href = getNodeAttribute(this, 'href');
                var params = parseQueryString(href.substring(href.indexOf('?')+1));
                $(self.id + '_changefolder').value = params.folder;
                self.browse_submit(e);
            });
        });
        if ($(self.id + '_createfolder')) {
            connect($(self.id + '_createfolder'), 'onclick', self.createfolder_submit);
        }
        if (self.config.select) {
            self.connect_select_buttons();
        }
    }

    this.make_row_droppable = function(row) {
        new Droppable(row, {
            accept: ['icon-drag-current'],
            hoverclass: 'folderhover',
            ondrop: function (dragged, dropped) {
                var dragid = dragged.id.replace(/^.*drag:(\d+)$/, '$1');
                var dropid = dropped.id.replace(/^file:(\d+)$/, '$1');
                if (dragid == dropid) {
                    return;
                }
                $(self.id + '_move').value = dragid;
                $(self.id + '_moveto').value = dropid;
                signal(self.form, 'onsubmit');
                self.form.submit();
                $(self.id + '_move').value = '';
                $(self.id + '_moveto').value = '';
            }
        });
    };

    this.drag = {};

    this.make_icon_draggable = function(elem) {
        new Draggable(elem, {
            starteffect: function(elem) {
                if (!self.drag.clone) {
                    // Works better in IE if we just drag an empty div around without the child image.
                    // Otherwise the element seems to get dropped during the drag and we end up with a
                    // crossed-circle cursor.

                    self.drag.clone = DIV({'id':elem.id, 'class':'icon-drag'});
                    setNodeAttribute(elem, 'id', 'copy-of-' + elem.id);

                    removeElementClass(elem, 'icon-drag');
                    addElementClass(elem, 'icon-drag-current');

                    insertSiblingNodesAfter(elem, self.drag.clone);

                    var child = getFirstElementByTagAndClassName('img', null, elem);
                    var dimensions = elementDimensions(child);

                    removeElement(child);
                    appendChildNodes(self.drag.clone, child);

                    setStyle(elem, {
                        'position': 'absolute',
                        'border': '2px solid #aaa'
                    });
                    setElementDimensions(elem, dimensions);
                }
            },
            revert: function (element) {
                if (self.drag.clone) {
                    removeElement(element);
                    forEach(Draggables.drags, function(drag) {
                        if (drag.element == element) {
                            drag.destroy();
                        }
                    });
                    element = null;
                    self.make_icon_draggable(self.drag.clone);
                    self.drag = {};
                }
            }
        });
        // Draggable sets position = 'relative', but we set it back
        // here because with position = 'relative' in IE6 the rows
        // stay put instead of moving down when the create/upload
        // forms are opened on the page.
        elem.style.position = 'static';
    };

    this.select_init = function () {
        connect(self.id + '_open_upload_browse', 'onclick', function (e) {
            e.stop();
            removeElementClass(self.id + '_upload_browse', 'hidden');
            addElementClass(this, 'hidden');
            return false;
        });
        connect(self.id + '_close_upload_browse', 'onclick', function (e) {
            e.stop();
            addElementClass(self.id + '_upload_browse', 'hidden');
            removeElementClass(self.id + '_open_upload_browse', 'hidden');
            return false;
        });
        forEach(getElementsByTagAndClassName('button', 'unselect', self.id + '_selectlist'), function (elem) {
            connect(elem, 'onclick', self.unselect);
        });
        self.connect_select_buttons();
    }

    this.connect_select_buttons = function () {
        forEach(getElementsByTagAndClassName('button', 'select', 'filelist'), function (elem) {
            connect(elem, 'onclick', function (e) {
                e.stop();
                var id = this.name.replace(/.*_select\[(\d+)\]$/, '$1');
                if (!self.selecteddata[id]) {
                    self.add_to_selected_list(id);
                }
                return false;
            });
        });
    }

    this.add_to_selected_list = function (id, highlight) {
        var tbody = getFirstElementByTagAndClassName('tbody', null, self.id + '_selectlist');
        var rows = getElementsByTagAndClassName('tr', null, tbody);
        if (rows.length == 0) {
            removeElementClass(self.id + '_selectlist', 'hidden');
            addElementClass(self.id + '_empty_selectlist', 'hidden');
        }
        else if (highlight) {
            forEach(rows, function (r) { removeElementClass(r, 'highlight-file'); });
        }
        // var remove = BUTTON({'type':'submit', 'class':'button small unselect', 'name':'unselect[' + id + ']', 'value':id}, get_string('remove')); // IE problem ?
        var remove = INPUT({'type': 'submit', 'class':'button small unselect', 'name':self.id+'_unselect[' + id + ']', 'value':get_string('remove')});
        connect(remove, 'onclick', self.unselect);
        if (self.filedata[id].artefacttype == 'image') {
            var imgsrc = self.config.wwwroot + 'artefact/file/download.php?file=' + id + '&size=20x20';
        }
        else {
            var imgsrc = self.config.theme['images/' + self.filedata[id].artefacttype + '.gif'];
        }
        appendChildNodes(tbody, TR({'class': 'r' + rows.length % 2 + (highlight ? ' highlight-file' : '')},
                                   TD(null, IMG({'src':imgsrc})),
                                   TD(null, self.filedata[id].title),
                                   TD(null, self.filedata[id].description),
                                   TD(null, self.filedata[id].tags.join(', ')),
                                   TD(null, remove, INPUT({'type':'hidden', 'name':self.id+'_selected[' + id + ']', 'value':id}))
                                  ));
        self.selecteddata[id] = {
            'id': id,
            'artefacttype': self.filedata[id].artefacttype,
            'title': self.filedata[id].title,
            'description': self.filedata[id].description
        };
        if (self.filedata[id].tags) {
            self.selecteddata[id].tags = self.filedata[id].tags;
        }
    }

    this.unselect = function (e) {
        e.stop();
        var id = this.name.replace(/.*_unselect\[(\d+)\]$/, '$1');
        delete self.selecteddata[id];
        removeElement(getFirstParentByTagAndClassName(this, 'tr'));
        var rows = getElementsByTagAndClassName('tr', null, getFirstElementByTagAndClassName('tbody', null, self.id + '_selectlist'));
        if (rows.length == 0) {
            addElementClass(self.id + '_selectlist', 'hidden');
            removeElementClass(self.id + '_empty_selectlist', 'hidden');
        }
        else {
            // Fix row classes
            for (var r = 0; r < rows.length; r++) {
                setNodeAttribute(rows[r], 'class', 'r' + r % 2);
            }
        }
        return false;
    }

    this.success = function (form, data) {
        if (data.uploaded) {
            self.upload_success(data);  // Remove uploading message
        }
        // Only update the file listing if the user hasn't changed folders yet
        if (data.newlist && (data.folder == self.folderid || data.changedfolder)) {
            self.filedata = data.newlist.data;
            if (self.config.edit) {
                replaceChildNodes(self.id + '_edit_placeholder', removeElement(self.id + '_edit_row'));
            }
            $(self.id+'_filelist_container').innerHTML = data.newlist.html;
            if (data.changedfolder && data.newpath) {
                $(self.id+'_folder').value = self.folderid = data.folder;
                $(self.id+'_foldername').value = self.foldername = data.newpath.foldername;
                $(self.id+'_foldernav').innerHTML = data.newpath.html;
            }
            else if (data.uploaded && self.config.select && data.highlight) {
                // Newly uploaded files should be automatically selected
                self.add_to_selected_list(data.highlight, true);
            }
            else if (data.deleted) {
                quotaUpdate(data.quotaused, data.quota);
            }
            self.browse_init();
        }
        else if (data.goto) {
            location.href = data.goto;
        }
        else if (typeof(data.replaceHTML) == 'string') {
            formSuccess(form, data);
            self.init();
        }
    }

}

/* 
// Check if there's already a file attached to the post with the given name
function fileattached(filename) {
    return some(map(function (e) { return e.childNodes[1]; }, attached.tbody.childNodes),
                function (cell) { return scrapeText(cell) == filename; });
}


// Check if there's already a file attached to the post with the given id
function fileattached_id(id) {
    return some(attached.tbody.childNodes, function (r) { return getNodeAttribute(r,'id') == id; });
}
*/
