pimcore.registerNS("pimcore.plugin.SEInternationalisation");
pimcore.plugin.SEInternationalisation = Class.create(pimcore.plugin.admin, {
    getClassName: function() {
        return "pimcore.plugin.SEInternationalisation";
    },

    initialize: function() {
        pimcore.plugin.broker.registerPlugin(this);
    },
	
    postOpenDocument: function(document, type) {
    	
    	//document.properties.languagesPanel.hide();		// hide language property
    	
    	document.properties.languagesPanel.form.items.items[0].disabled = true;		// disable language property
    	
    	/*
	    	// dit is voor de SEBasic plugin (indien geen admin)
	    	document.properties.navigationPanel.items.items[1].hide();
	    	document.settings.layout.items.items[1].hide();
	    	document.settings.layout.items.items[2].hide();
	    	// tot hier
    	*/
    	
    	
    	// add checkbox: idem voor alle talen bij properties
    	var checked = false;
    	for (o in document.data.properties) {
	        // Get vars
			if (o.substr(0, 22) == 'se_i18n_prop_chk_same_') {
		   		checked = true;
		   		if (o.substr(22) != document.id) {
		   			document.properties.disallowedKeys[document.properties.disallowedKeys.length] = o;
		   		}
			}
    	}														// TODO op checked zetten bij <ADD>
    	var checkbox = {
    			xtype: 'checkbox',
    			id: 'prop_chk_same_' + document.id, 
        		name: 'prop_chk_same_' + document.id, 
        		boxLabel: ts('same_for_all_languages'), 
        		hideLabels: false,
        		checked: checked
    	};
    	// Add Checkbox
    	this.addSpacerAndCheckbox(checkbox, document);
    	
    	// Add SaveToAllLanguages Button
//    	this.addSaveToAllBtn(document);
    	
    	// fix, zie issue http://www.pimcore.org/issues/browse/PIMCORE-1382, niet meer nodig voor versie 1.4.4
    	document.properties.disallowedKeys[document.properties.disallowedKeys.length] = 'navigation_tabindex';
    	document.properties.disallowedKeys[document.properties.disallowedKeys.length] = 'navigation_accesskey';
    	
    	// Set this document as global
    	globalDocument = document;

        var me = this;

        Ext.Ajax.request({
            url: '/plugin/SEInternationalisation/document/get-all-documents/',
            method: "post",
            params: {"id": document.id},
            success: function (response) {
                var rdata = Ext.decode(response.responseText);
                if (rdata) {
                    var btns = [];
                    for(var i in rdata) {
                        if (rdata[i].id !== undefined && document.properties.getPropertyData('language') !== rdata[i].language) {
                            btns.push( {
                                text: t('lang-' + rdata[i].language),
                                iconCls: "pimcore_icon_cursor",
                                docID:rdata[i].id,
                                docType:document.type,
                                handler: function() {me.openOtherDocument(this.docID,this.docType)}
                            });
                        }
                    }

                    var menu = {
                        text: t('Open in other language'),
                            iconCls: "pimcore_icon_cursor",
                        scale: "medium",
                        menu: btns
                    };


                    var position = document.toolbar.items.length;
                    document.toolbar.insert(position,"-");
                    position++;
                    document.toolbar.insert(position,menu);
                    pimcore.layout.refresh();
                }
            }.bind(this)
        });
    },

    openOtherDocument:function(id,type) {
        pimcore.helpers.openDocument(id, type);
    },
    
    addSpacerAndCheckbox: function(checkbox, document) {
    	var pg = document.properties.propertyGrid;
    	
    	var config = [
    	       {
    	    	   xtype: 'tbspacer',
    	    	   width: 20
    	       },
    	      '-',
    	       {
    	    	   xtype: 'tbspacer',
    	    	   width: 20
    	       },
    	       checkbox
    	];
    	pg.toolbars[0].add(config);

    	var me = this;
    	Ext.getCmp('prop_chk_same_' + document.id).on('check', function() {me.setHiddenValue(this);});
    	
    	pg.doLayout();
    	// + toevoegen aan de languagePanel Form
    	var hiddenField = new Ext.form.Hidden({
    		id: 'se_i18n_'+checkbox.id,
    		name: 'se_i18n_'+checkbox.name,
    		value: '1'
    	});
    	document.properties.languagesPanel.form.add(hiddenField);
    	document.properties.disallowedKeys[document.properties.disallowedKeys.length] = 'se_i18n_' + checkbox.id;
    	
    	document.properties.languagesPanel.doLayout();
    },
    
    setHiddenValue: function(checkbox) {
    	if (checkbox.getValue()) {
    		Ext.getCmp('se_i18n_' + checkbox.id).setValue(true);
    	} else {
    		Ext.getCmp('se_i18n_' + checkbox.id).setValue(false);
    	}
    },
    
    addSaveToAllBtn: function(document) {
    	var me = this;
    	var btn = {
    			id: 'copy_to_all_' + document.id, 
        		text: ts('copy_to_all_languages'),
        		iconCls: "pimcore_icon_save",
        		handler: me.copyToAllLanguages
    	};
//    	document.toolbar.add(btn);
    	document.toolbar.get(1).menu.add(btn);
    },
    
    copyToAllLanguages: function(context) {
    	// Publish this document first
//    	globalDocument.publish();
    	
//    	var id = globalDocument.id;
    	var id = this.id.replace("copy_to_all_","");
    	Ext.Ajax.request({
            url: '/plugin/SEInternationalisation/document/copytoall/',
            method: "post",
            params: {"id": id},
            success: function (response) {
                try{
                    var rdata = Ext.decode(response.responseText);
                    if (rdata && rdata.success) {
                        pimcore.helpers.showNotification(t("success"), ts("successful_saved_document_in_all_languages"), "success");
                        
                        // Reload all open documents
                        if (rdata.documents) {
                        	var tabPanel = Ext.getCmp("pimcore_panel_tabs");
                        	for(var i in rdata.documents) {
                        		if (pimcore.globalmanager.exists("document_" + rdata.documents[i])) {
                        			tabPanel.remove(pimcore.globalmanager.get("document_" + rdata.documents[i]).tab);
                        		}
                        	}
                        }
                    }
                    else {
                        pimcore.helpers.showNotification(t("error"), ts("error_saving_document_in_all_languages"), "error",t(rdata.message));
                    }
                } catch (e) {
                    pimcore.helpers.showNotification(t("error"), ts("error_saving_document_in_all_languages"), "error");
                }
            }.bind(this)
        });
    }
});

var SEInternationalisation = new pimcore.plugin.SEInternationalisation();

var globalDocument;