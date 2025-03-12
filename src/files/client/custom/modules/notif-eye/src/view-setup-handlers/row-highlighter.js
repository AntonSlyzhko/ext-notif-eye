import {Events} from 'bullbone';

/**
 * @mixes Bull.Events
 */
class RowHighlighter {

    /**
     * @param {import('views/record/detail').default} view
     */
    constructor(view) {
        this.view = view;
    }

    process() {
        const streamLevel = this.view.getAcl().getLevel(this.view.entityType, 'stream');
        if (!this.view.getMetadata().get(['scopes', this.view.entityType, 'stream'], false) ||
            !this.view.getMetadata().get(['scopes', this.view.entityType, 'notifEye'], false) ||
            !streamLevel ||
            streamLevel === 'no'
        ) {
            return;
        }

        this.view.buildRow = function (i, model, callback) {
            const key = model.id;
            const hasUnreadNotifications = model.get('hasUnreadNotifications');

            this.rowList.push(key);

            this.getInternalLayout(internalLayout => {
                internalLayout = Espo.Utils.cloneDeep(internalLayout);

                this.prepareInternalLayout(internalLayout, model);

                const acl = {
                    edit: this.getAcl().checkModel(model, 'edit') && !this.editDisabled,
                    delete: this.getAcl().checkModel(model, 'delete') && !this.removeDisabled,
                };

                const viewName = hasUnreadNotifications ? 'notif-eye:views/record/list-row' : 'views/base';

                this.createView(key, viewName, {
                    model: model,
                    acl: acl,
                    rowActionHandlers: this._rowActionHandlers || {},
                    selector: this.getRowSelector(key),
                    optionsToPass: ['acl', 'rowActionHandlers'],
                    layoutDefs: {
                        type: this._internalLayoutType,
                        layout: internalLayout,
                    },
                    setViewBeforeCallback: this.options.skipBuildRows && !this.isRendered(),
                }, callback)
                    .then(view => {
                        if (!hasUnreadNotifications) {
                            return;
                        }
                        this.listenToOnce(view, 'record-visited', (o) => {
                            let id = o.id;
                            if (!id) {
                                return;
                            }
                            this.collection.get(id).set('hasUnreadNotifications', false);
                        });
                    })
            }, model);
        }
    }
}

Object.assign(RowHighlighter.prototype, Events);

// noinspection JSUnusedGlobalSymbols
export default RowHighlighter;
