pimcore.registerNS("pimcore.plugin.Multilingual");
pimcore.plugin.Multilingual = Class.create(pimcore.plugin.admin, {
    getClassName: function () {
        return "pimcore.plugin.Multilingual";
    },

    initialize: function () {
        pimcore.plugin.broker.registerPlugin(this);
    },

    pimcoreReady: function () {
        // Set the document retrieval url to the plugin
        var dataUrl = '/plugin/Multilingual/index/tree-get-childs-by-id/';
        pimcore.globalmanager.get("layout_document_tree").treeDataUrl = dataUrl;
        pimcore.globalmanager.get("layout_document_tree").tree.getStore().proxy.url = dataUrl;

        /*
         * because the initial load of the tree can't be skipped and ajax is async, we can't make
         * sure that our call ends sooner than the initial call. This can result in all languages
         * being loaded into the tree instead of only the selected one.
         * We listen for the load event (= tree was fully loaded) and if the url didn't match our
         * plugin's url we load our language.
         * This is kinda hackish but looks to be working as it should
         */
        var self = this;
        pimcore.globalmanager.get("layout_document_tree").tree.getStore().on('load', function() {
            var loadedUrl = arguments[3].getRequest().getUrl();
            if (typeof loadedUrl === 'string' && loadedUrl.substring(0, dataUrl.length) !== dataUrl) {
                self.loadLanguageInitial();
            }
        });


        // Set the language selector in the documents header
        this.setHeader();
    },

    postOpenDocument: function (document, type) {
        document.properties.languagesPanel.items.items[0].disabled = true;		// disable language property
    },

    setHeader: function () {
        // Create selectbox html
        var html = '<select name="documentLanguage" id="documentLanguage">';

        // Loop through the store
        for (var i = 0; i < pimcore.settings.websiteLanguages.length; i++) {
            var id = pimcore.settings.websiteLanguages[i];
            var lang = pimcore.available_languages[id];

            // Create language option in selectbox
            html += '<option value="' + id + '">' + lang + '</option>';
        }

        // Close select tag
        html += '</select>';

        // Get document panel
        var el = Ext.getCmp('pimcore_panel_tree_documents');

        // Set title
        el.setTitle(el.title + ' ' + html);

        // set the default value
        document.getElementById('documentLanguage').value = pimcore.settings.websiteLanguages[0];

        // Load the requested language on the change event of our selectbox
        var me = this;
        var documentLanguageElement = Ext.get('documentLanguage');
        documentLanguageElement.on('change', function () {
            me.loadLanguage();
        });

        // Make sure the accordion doesn't collapse on click
        documentLanguageElement.on('click', function (e) {
            e.stopEvent();
        });

        documentLanguageElement.dom.setAttribute("class", "");
        documentLanguageElement.addCls("pimcore_icon_language_" + this.getSelectedLanguage().toLowerCase());
    },

    loadLanguage: function (callback) {
        pimcore.globalmanager.get("layout_document_tree").tree.getStore().proxy.setExtraParam('language', this.getSelectedLanguage());

        var self = this;
        var tree = pimcore.globalmanager.get("layout_document_tree").tree;
        var rootNode = tree.getRootNode();
        var ownerTree = rootNode.getOwnerTree();

        document.getElementById('documentLanguage').disabled = true;
        var documentLanguageElement = Ext.get('documentLanguage');
        ownerTree.getStore().load({
            node: rootNode,
            callback: function() {
                // update the language flag
                document.getElementById('documentLanguage').disabled = false;
                documentLanguageElement.setCls("pimcore_icon_language_" + self.getSelectedLanguage().toLowerCase());

                if (callback) {
                    callback();
                }
            }
        });
    },

    loadLanguageInitial: function () {
        var self = this;
        setTimeout(function() {
            pimcore.globalmanager.get("layout_document_tree").tree.getStore().proxy.setExtraParam('language', pimcore.available_languages[0]);
            self.loadLanguage();
        }, 1);


    },

    getSelectedLanguage: function () {
        return Ext.get('documentLanguage').getValue();
    },


});

var Multilingual = new pimcore.plugin.Multilingual();
