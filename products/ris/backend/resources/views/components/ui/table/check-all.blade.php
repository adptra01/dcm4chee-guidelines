<div 
    x-data="{
        init() {
            this.$wire.$watch('selectedIds', () => {
                this.syncHeaderCheckboxState()
            })

            this.$wire.$watch('visibleIds', () => {
                this.syncHeaderCheckboxState()
            })

            this.$wire.on('selectAll', () => {
                this.selectAllVisible()
            })

            this.$wire.on('deselectAll', () => {
                this.clearSelection()
            })
        },

        syncHeaderCheckboxState() {
            const checkbox = this.$refs.checkbox

            if (this.allVisibleSelected()) {
                checkbox.checked = true
                checkbox.indeterminate = false
                checkbox.dataset.state = 'checked'
            } else if (this.noSelection()) {
                checkbox.checked = false
                checkbox.indeterminate = false
                checkbox.dataset.state = 'empty'
            } else {
                checkbox.checked = false
                checkbox.indeterminate = true
                checkbox.dataset.state = 'indeterminate'
            }
        },

        allVisibleSelected() {
            return this.$wire.visibleIds.length > 0 && this.$wire.visibleIds.every(
                id => this.$wire.selectedIds.includes(id)
            )
        },

        noSelection() {
            return this.$wire.selectedIds.length === 0
        },

        handleHeaderToggle(e) {
            e.target.checked ? this.selectAllVisible() : this.clearSelection()
        },

        selectAllVisible() {
            this.$wire.visibleIds.forEach(id => {
                if (!this.$wire.selectedIds.includes(id)) {
                    this.$wire.selectedIds.push(id)
                }
            })
        },

        clearSelection() {
            this.$wire.selectedIds = []
        },
    }"
    {{ $attributes }}
>
    <x-ui.table.checkbox
        x-on:change="handleHeaderToggle"
        x-ref="checkbox"
        wire:key="checkAll"
    />
</div>
