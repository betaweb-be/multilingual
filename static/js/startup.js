pimcore.registerNS("pimcore.plugin.Multilingual");
pimcore.plugin.Multilingual = Class.create(pimcore.plugin.admin, {
    getClassName: function () {
        return "pimcore.plugin.Multilingual";
    },

    initialize: function () {
        pimcore.plugin.broker.registerPlugin(this);
    },

    pimcoreReady: function () {
        // Set the language selector in the documents header
        this.setHeader();

        // Set the document retrieval url to the plugin
        pimcore.globalmanager.get("layout_document_tree").tree.loader.dataUrl = '/plugin/Multilingual/index/tree-get-childs-by-id/';

        // Load initial language selection
        this.loadLanguageInitial();
    },

    postOpenDocument: function (document, type) {
        document.properties.languagesPanel.form.items.items[0].disabled = true;		// disable language property
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

        // Load the requested language on the change event of our selectbox
        var me = this;
        var documentLanguageElement = Ext.get('documentLanguage');
        documentLanguageElement.on('change', function () {
            me.loadLanguage();
            documentLanguageElement.dom.setAttribute("class", "");
            documentLanguageElement.addClass("pimcore_icon_language_" + me.getSelectedLanguage().toLowerCase());
        });

        // Make sure the accordion doesn't collapse on click
        documentLanguageElement.on('click', function () {
            el.collapse();
        });

        documentLanguageElement.dom.setAttribute("class", "");
        documentLanguageElement.addClass("pimcore_icon_language_" + this.getSelectedLanguage().toLowerCase());
    },

    loadLanguage: function () {
        document.getElementById('documentLanguage').disabled = true;
        pimcore.globalmanager.get("layout_document_tree").tree.getLoader().baseParams.language = this.getSelectedLanguage();
        pimcore.globalmanager.get("layout_document_tree").tree.getLoader().load(pimcore.globalmanager.get("layout_document_tree").tree.root, function () {
            document.getElementById('documentLanguage').disabled = false;
            pimcore.globalmanager.get("layout_document_tree").tree.root.expand();
        });
    },

    loadLanguageInitial: function () {
        document.getElementById('documentLanguage').disabled = true;
        pimcore.globalmanager.get("layout_document_tree").tree.getLoader().baseParams.language = this.getSelectedLanguage();
        pimcore.globalmanager.get("layout_document_tree").tree.getLoader().load(pimcore.globalmanager.get("layout_document_tree").tree.root, function () {
            document.getElementById('documentLanguage').disabled = false;
            pimcore.globalmanager.get("layout_document_tree").tree.getLoader().load(pimcore.globalmanager.get("layout_document_tree").tree.root);
            pimcore.globalmanager.get("layout_document_tree").tree.root.expand();
        });
    },

    getSelectedLanguage: function () {
        return Ext.get('documentLanguage').getValue();
    }

});

var Multilingual = new pimcore.plugin.Multilingual();
