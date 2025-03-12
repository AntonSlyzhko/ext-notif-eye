import BaseView from 'views/base';

class ListRow extends BaseView {

    isVisited = false;

    setup() {
        super.setup();
        this.entityType = this.model.entityType;
        this.subscribeToRouterEvent();
    }

    subscribeToRouterEvent() {
        this.listenToOnce(this.getRouter(), 'routed', (options) => {
            if (this.isRecordVisited(options)) {
                this.isVisited = true;
                this.trigger('record-visited', {id: this.model.id});
                return;
            }
            this.subscribeToRouterEvent();
        });
    }

    isRecordVisited(routerOptions) {
        let id = routerOptions.options?.id;
        let controller = routerOptions.controller;
        let action = routerOptions.action;

        return  controller === this.entityType
            && action === 'view'
            && id === this.model.id;
    }

    afterRender() {
        super.afterRender();
        if (!this.isVisited) {
            this.$el?.addClass('bg-warning');
            return;
        }
        this.$el?.removeClass('bg-warning');
    }
}

export default ListRow;
