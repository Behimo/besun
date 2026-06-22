<script setup>
import FullCalendar from '@fullcalendar/vue3'
import { CRM_CALENDAR_FILTERS, useCrmCalendar } from '@/views/crm/tasks/useCrmCalendar'

const emit = defineEmits(['select-task', 'create-task'])

const { isLeftSidebarOpen } = useResponsiveLeftSidebar()

const {
  refCalendar,
  calendarOptions,
  refetchEvents,
  jumpToDate,
  selectedCategories,
  checkAll,
} = useCrmCalendar({
  isLeftSidebarOpen,
  onTaskClick: id => emit('select-task', id),
  onCreateTask: date => emit('create-task', date),
})

defineExpose({ refresh: refetchEvents })

const sidebarDate = ref(new Date().toJSON().slice(0, 10))

const onSidebarDateChange = val => {
  sidebarDate.value = val
  jumpToDate(val)
}
</script>

<template>
  <VCard class="crm-task-calendar-card">
    <VLayout style="z-index: 0;">
      <VNavigationDrawer
        v-model="isLeftSidebarOpen"
        data-allow-mismatch
        width="320"
        absolute
        touchless
        location="start"
        class="calendar-add-event-drawer"
        :temporary="$vuetify.display.mdAndDown"
      >
        <div class="pa-6">
          <VBtn
            block
            prepend-icon="tabler-plus"
            @click="emit('create-task')"
          >
            تسک جدید
          </VBtn>
        </div>

        <VDivider />

        <div class="pa-4 px-6">
          <AppJalaliMiniCalendar
            :model-value="sidebarDate"
            @update:model-value="onSidebarDateChange"
          />
        </div>

        <VDivider />

        <div class="pa-6">
          <h6 class="text-h6 font-weight-medium mb-4">
            فیلتر رویدادها
          </h6>

          <div class="d-flex flex-column calendars-checkbox gap-1">
            <VCheckbox
              id="crm-calendar-check-all"
              v-model="checkAll"
              label="نمایش همه"
              hide-details
            />
            <VCheckbox
              v-for="(filter, index) in CRM_CALENDAR_FILTERS"
              :id="`crm-calendar-filter-${index}`"
              :key="filter.category"
              v-model="selectedCategories"
              :value="filter.category"
              :color="filter.color"
              :label="filter.label"
              hide-details
            />
          </div>
        </div>

        <VDivider />

        <div class="pa-6">
          <p class="text-body-2 text-medium-emphasis mb-3">
            راهنما
          </p>
          <div class="d-flex flex-column gap-2">
            <div
              v-for="filter in CRM_CALENDAR_FILTERS"
              :key="`legend-${filter.category}`"
              class="d-flex align-center gap-2"
            >
              <VAvatar
                :color="filter.color"
                variant="tonal"
                size="28"
                rounded
              >
                <VIcon
                  :icon="filter.category === 'Business' ? 'tabler-phone' : filter.category === 'Holiday' ? 'tabler-check' : filter.category === 'Personal' ? 'tabler-alert-circle' : filter.category === 'Family' ? 'tabler-checkbox' : 'tabler-note'"
                  size="16"
                />
              </VAvatar>
              <span class="text-body-2">{{ filter.label }}</span>
            </div>
          </div>
        </div>
      </VNavigationDrawer>

      <VMain>
        <FullCalendar
          ref="refCalendar"
          :options="calendarOptions"
        />
      </VMain>
    </VLayout>
  </VCard>
</template>

<style lang="scss">
@use "@core-scss/template/libs/full-calendar";

.crm-task-calendar-card {
  overflow: visible;

  .fc-view-harness {
    min-block-size: 48rem;
  }

  .fc-daygrid-day {
    min-block-size: 7rem;
  }

  .fc-daygrid-day-top .fc-daygrid-day-number {
    display: none;
  }

  .fc-daygrid-day-frame {
    position: relative;
  }

  .fc-daygrid-day-number {
    font-size: 1rem;
    font-weight: 600;
  }

  .fc-jalali-day {
    position: absolute;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    block-size: 1.75rem;
    font-size: 0.9375rem;
    font-weight: 600;
    inline-size: 1.75rem;
    inset-block-start: 6px;
    inset-inline-end: 8px;

    &--today {
      background: rgba(var(--v-theme-primary), 0.16);
      color: rgb(var(--v-theme-primary));
      font-weight: 700;
    }
  }

  .fc-toolbar-title {
    font-size: 1.125rem;
    font-weight: 600;
  }

  .calendars-checkbox .v-label {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    opacity: var(--v-high-emphasis-opacity);
  }
}

.calendar-add-event-drawer {
  &.v-navigation-drawer:not(.v-navigation-drawer--temporary) {
    border-end-start-radius: 0.375rem;
    border-start-start-radius: 0.375rem;
  }

  &.v-navigation-drawer--temporary:not(.v-navigation-drawer--active) {
    transform: translateX(-110%) !important;
  }
}

@media screen and (max-width: 1279px) {
  .calendar-add-event-drawer {
    border-width: 0;
  }
}
</style>

<style lang="scss" scoped>
.v-layout {
  overflow: visible !important;
}
</style>
