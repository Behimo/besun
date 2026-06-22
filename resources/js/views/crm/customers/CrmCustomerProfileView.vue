<script setup>
import CrmConvertLeadDialog from '@/components/crm/CrmConvertLeadDialog.vue'
import CrmEntityProductsDialog from '@/components/crm/CrmEntityProductsDialog.vue'
import CrmHandoffDialog from '@/components/crm/CrmHandoffDialog.vue'
import CrmHandoffReturnDialog from '@/components/crm/CrmHandoffReturnDialog.vue'
import CrmProductChips from '@/components/crm/CrmProductChips.vue'
import CrmSmsSendDialog from '@/components/crm/CrmSmsSendDialog.vue'

const { hasModule, userData } = useAppShell()
const canSendSms = computed(() => hasModule('mod-sms') && (userData.value?.permissions?.includes('sms.send') || userData.value?.tenant?.isOwner))
const smsDialog = ref(false)

const router = useRouter()

const {
  profile,
  loading,
  saving,
  tab,
  teamUsers,
  salesStages,
  marketingStages,
  currentUserId,
  saveContact,
  addActivity,
  addTask,
  completeHandoff,
  formatDt,
  goToMarketingFunnel,
  goToDeals,
  openDealHandoff,
  openLeadHandoff,
  fetchProfile,
} = useCrmCustomerProfile()

const editDialog = ref(false)
const editForm = ref({})
const activityDialog = ref(false)
const taskDialog = ref(false)
const handoffDialog = ref(false)
const returnDialog = ref(false)
const handoffConfig = ref({ entityType: 'deal', entity: null, stages: [], preset: 'assign' })
const returningHandoff = ref(null)
const productsDialog = ref(false)
const productsEntityType = ref('lead')
const productsEntity = ref(null)
const convertDialog = ref(false)
const convertingLead = ref(null)

const openLeadProducts = lead => {
  productsEntityType.value = 'lead'
  productsEntity.value = lead
  productsDialog.value = true
}

const openDealProducts = deal => {
  productsEntityType.value = 'deal'
  productsEntity.value = deal
  productsDialog.value = true
}

const openConvert = lead => {
  convertingLead.value = lead
  convertDialog.value = true
}

const onProductsSaved = () => fetchProfile()

const onConvertSuccess = () => fetchProfile()

const activityForm = ref({ type: 'call', subject: '', body: '', scheduleFuture: false, scheduled_date: '', scheduled_time: '09:00' })
const taskForm = ref({ title: '', description: '', due_date: '', due_time: '09:00', priority: 'medium', assignee_id: null })

const { mergeDatetime } = useFollowUpDatetime()

const openEdit = () => {
  if (!profile.value?.contact)
    return

  editForm.value = { ...profile.value.contact }
  editDialog.value = true
}

const submitEdit = async () => {
  await saveContact(editForm.value)
  editDialog.value = false
}

const submitActivity = async () => {
  const body = {
    type: activityForm.value.type,
    subject: activityForm.value.subject,
    body: activityForm.value.body,
  }

  if (activityForm.value.scheduleFuture && activityForm.value.scheduled_date) {
    body.scheduled_at = mergeDatetime(activityForm.value.scheduled_date, activityForm.value.scheduled_time)
  }

  await addActivity(body)
  activityDialog.value = false
  activityForm.value = { type: 'call', subject: '', body: '', scheduleFuture: false, scheduled_date: '', scheduled_time: '09:00' }
}

const submitTask = async () => {
  await addTask({
    title: taskForm.value.title,
    description: taskForm.value.description || undefined,
    priority: taskForm.value.priority,
    assignee_id: taskForm.value.assignee_id || undefined,
    due_at: taskForm.value.due_date
      ? mergeDatetime(taskForm.value.due_date, taskForm.value.due_time)
      : undefined,
  })
  taskDialog.value = false
  taskForm.value = { title: '', description: '', due_date: '', due_time: '09:00', priority: 'medium', assignee_id: null }
}

const showHandoff = (config, preset = 'assign') => {
  handoffConfig.value = { ...config, preset }
  handoffDialog.value = true
}

const onHandoffSuccess = () => fetchProfile()

const openReturn = handoff => {
  returningHandoff.value = handoff
  returnDialog.value = true
}

const handoffTypeLabel = type => ({
  assign: 'واگذاری',
  finance: 'مالی',
  return: 'بازگشت',
}[type] ?? type)

const taskStatusLabel = status => ({
  pending: 'در انتظار',
  in_progress: 'در حال انجام',
  completed: 'انجام‌شده',
}[status] ?? status)

const typeLabel = type => ({
  call: 'تماس',
  meeting: 'جلسه',
  note: 'یادداشت',
}[type] ?? type)

const leadStatusLabel = status => ({
  new: 'جدید',
  converted: 'تبدیل‌شده',
}[status] ?? status)

const { formatTimeSpent, formatWorkRange } = useCrmTasks()
</script>

<template>
  <div v-if="loading">
    <VProgressLinear
      indeterminate
      color="primary"
      class="mb-4"
    />
  </div>

  <VAlert
    v-else-if="!profile"
    type="error"
    variant="tonal"
  >
    پروفایل یافت نشد.
    <VBtn
      class="mt-3"
      variant="tonal"
      @click="router.push({ name: 'apps-crm-contacts' })"
    >
      بازگشت به مخاطبین
    </VBtn>
  </VAlert>

  <template v-else>
    <VCard class="mb-5">
      <VCardText class="pa-6">
        <div class="d-flex align-start flex-wrap gap-4">
          <VAvatar
            size="80"
            color="primary"
            variant="tonal"
            rounded
          >
            <span class="text-h4">{{ profile.contact.name?.charAt(0) }}</span>
          </VAvatar>

          <div class="flex-grow-1">
            <div class="d-flex align-center flex-wrap gap-2 mb-2">
              <h4 class="text-h4 mb-0">
                {{ profile.contact.name }}
              </h4>
              <VChip
                v-if="profile.active_lead"
                size="small"
                color="info"
                variant="tonal"
              >
                لید فعال
              </VChip>
            </div>
            <div class="text-body-2 text-medium-emphasis mb-1">
              <VIcon
                icon="tabler-mail"
                size="16"
                class="me-1"
              />
              {{ profile.contact.email || '—' }}
              <span class="mx-2">|</span>
              <VIcon
                icon="tabler-phone"
                size="16"
                class="me-1"
              />
              {{ profile.contact.phone || '—' }}
            </div>
            <div class="text-body-2">
              {{ profile.contact.company || '—' }}
              <span v-if="profile.contact.job_title"> — {{ profile.contact.job_title }}</span>
              <span v-if="profile.contact.city"> — {{ profile.contact.city }}</span>
            </div>
            <div
              v-if="profile.contact.assignee"
              class="text-caption text-medium-emphasis mt-2"
            >
              مسئول: {{ profile.contact.assignee.name }}
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <VBtn
              variant="tonal"
              prepend-icon="tabler-pencil"
              @click="openEdit"
            >
              ویرایش
            </VBtn>
            <VBtn
              variant="tonal"
              prepend-icon="tabler-checkbox"
              @click="taskDialog = true"
            >
              تسک جدید
            </VBtn>
            <VBtn
              variant="tonal"
              prepend-icon="tabler-calendar-plus"
              @click="activityDialog = true"
            >
              ثبت فعالیت
            </VBtn>
            <VBtn
              v-if="canSendSms && profile.contact.phone"
              variant="tonal"
              prepend-icon="tabler-message"
              @click="smsDialog = true"
            >
              پیامک
            </VBtn>
            <VBtn
              v-if="profile.active_lead"
              variant="tonal"
              color="info"
              prepend-icon="tabler-layout-kanban"
              @click="goToMarketingFunnel()"
            >
              قیف بازاریابی
            </VBtn>
            <VBtn
              variant="tonal"
              color="success"
              prepend-icon="tabler-chart-funnel"
              @click="goToDeals()"
            >
              قیف فروش
            </VBtn>
          </div>
        </div>

        <div class="mt-4">
          <div class="text-subtitle-2 font-weight-medium mb-3">
            محصولات قیف
          </div>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VCard
                variant="tonal"
                color="info"
              >
                <VCardText>
                  <div class="d-flex align-center justify-space-between mb-2">
                    <span class="text-body-2 font-weight-medium">قیف بازاریابی</span>
                    <VBtn
                      v-if="profile.funnel_products?.marketing?.lead_id"
                      size="x-small"
                      variant="text"
                      @click="openLeadProducts({ id: profile.funnel_products.marketing.lead_id, name: profile.active_lead?.name })"
                    >
                      ویرایش
                    </VBtn>
                  </div>
                  <div
                    v-if="profile.funnel_products?.marketing?.stage"
                    class="text-caption text-medium-emphasis mb-2"
                  >
                    مرحله: {{ profile.funnel_products.marketing.stage.name }}
                  </div>
                  <CrmProductChips
                    v-if="profile.funnel_products?.marketing?.products?.length"
                    :products="profile.funnel_products.marketing.products"
                    :max="6"
                  />
                  <span
                    v-else
                    class="text-caption text-medium-emphasis"
                  >
                    محصولی ثبت نشده
                  </span>
                </VCardText>
              </VCard>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VCard
                variant="tonal"
                color="success"
              >
                <VCardText>
                  <div class="text-body-2 font-weight-medium mb-2">
                    قیف فروش
                  </div>
                  <template v-if="profile.funnel_products?.sales?.length">
                    <div
                      v-for="deal in profile.funnel_products.sales"
                      :key="deal.deal_id"
                      class="mb-3"
                    >
                      <div class="d-flex align-center justify-space-between mb-1">
                        <span class="text-caption font-weight-medium">{{ deal.title }}</span>
                        <VBtn
                          size="x-small"
                          variant="text"
                          @click="openDealProducts({ id: deal.deal_id, title: deal.title })"
                        >
                          ویرایش
                        </VBtn>
                      </div>
                      <div
                        v-if="deal.stage"
                        class="text-caption text-medium-emphasis mb-1"
                      >
                        {{ deal.stage.name }}
                      </div>
                      <CrmProductChips
                        v-if="deal.products?.length"
                        :products="deal.products"
                        :max="4"
                      />
                      <span
                        v-else
                        class="text-caption text-medium-emphasis"
                      >
                        محصولی ثبت نشده
                      </span>
                    </div>
                  </template>
                  <span
                    v-else
                    class="text-caption text-medium-emphasis"
                  >
                    معامله باز با محصول ثبت نشده
                  </span>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <VRow class="mt-4">
          <VCol
            cols="6"
            sm="3"
          >
            <div class="text-caption text-medium-emphasis">
              لیدها
            </div>
            <div class="text-h6">
              {{ profile.stats.leads_count }}
            </div>
          </VCol>
          <VCol
            cols="6"
            sm="3"
          >
            <div class="text-caption text-medium-emphasis">
              معاملات
            </div>
            <div class="text-h6">
              {{ profile.stats.deals_count }}
            </div>
          </VCol>
          <VCol
            cols="6"
            sm="3"
          >
            <div class="text-caption text-medium-emphasis">
              ارزش معاملات
            </div>
            <div class="text-h6 text-primary">
              {{ Number(profile.stats.deals_total_amount).toLocaleString('fa-IR') }}
            </div>
          </VCol>
          <VCol
            cols="6"
            sm="3"
          >
            <div class="text-caption text-medium-emphasis">
              تسک باز
            </div>
            <div class="text-h6">
              {{ profile.stats.open_tasks }}
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VAlert
      v-if="profile.pending_handoffs?.length"
      type="warning"
      variant="tonal"
      class="mb-5"
      title="واگذاری‌های در انتظار شما"
    >
      <VList density="compact">
        <VListItem
          v-for="handoff in profile.pending_handoffs"
          :key="handoff.id"
          :title="`${handoffTypeLabel(handoff.handoff_type)} — ${handoff.entity_type === 'deal' ? 'معامله' : 'لید'} #${handoff.entity_id}`"
          :subtitle="`${handoff.from_user?.name ?? '—'} → ${handoff.to_stage?.name ?? '—'}${handoff.note ? ' — ' + handoff.note : ''}`"
        >
          <template #append>
            <VBtn
              size="small"
              variant="tonal"
              class="me-2"
              @click="openReturn(handoff)"
            >
              بازگرداندن
            </VBtn>
            <VBtn
              size="small"
              variant="text"
              @click="completeHandoff(handoff.id)"
            >
              تکمیل
            </VBtn>
          </template>
        </VListItem>
      </VList>
    </VAlert>

    <VCard>
      <VTabs v-model="tab">
        <VTab value="overview">
          خلاصه
        </VTab>
        <VTab value="marketing">
          بازاریابی
        </VTab>
        <VTab value="sales">
          فروش
        </VTab>
        <VTab value="activities">
          فعالیت‌ها
        </VTab>
        <VTab value="tasks">
          تسک‌ها
        </VTab>
        <VTab value="timeline">
          تایم‌لاین
        </VTab>
      </VTabs>

      <VCardText>
        <VWindow v-model="tab">
          <VWindowItem value="overview">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <h6 class="text-h6 mb-3">
                  اطلاعات تماس
                </h6>
                <p class="text-body-2 mb-2">
                  <strong>ثبت:</strong> {{ profile.contact.created_at_jalali }}
                </p>
                <p
                  v-if="profile.contact.notes"
                  class="text-body-2"
                >
                  <strong>یادداشت:</strong> {{ profile.contact.notes }}
                </p>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <h6 class="text-h6 mb-3">
                  لید فعال
                </h6>
                <VCard
                  v-if="profile.active_lead"
                  variant="tonal"
                  color="info"
                >
                  <VCardText>
                    <div class="font-weight-medium mb-1">
                      {{ profile.active_lead.marketing_stage?.name }}
                    </div>
                    <div class="text-body-2">
                      امتیاز: {{ profile.active_lead.score ?? '—' }} — {{ leadStatusLabel(profile.active_lead.status) }}
                    </div>
                    <div
                      v-if="profile.active_lead.next_follow_up_at"
                      class="text-caption mt-2"
                    >
                      پیگیری: {{ formatDt(profile.active_lead.next_follow_up_at) }}
                    </div>
                    <VBtn
                      v-if="profile.active_lead.is_ready_for_sales"
                      size="small"
                      color="success"
                      variant="tonal"
                      class="mt-3"
                      prepend-icon="tabler-arrow-forward"
                      @click="openConvert(profile.active_lead)"
                    >
                      ارجاع به فروش
                    </VBtn>
                  </VCardText>
                </VCard>
                <p
                  v-else
                  class="text-body-2 text-medium-emphasis"
                >
                  لید فعالی ثبت نشده است.
                </p>
              </VCol>
            </VRow>
          </VWindowItem>

          <VWindowItem value="marketing">
            <VDataTable
              :items="profile.leads"
              :headers="[
                { title: 'نام', key: 'name' },
                { title: 'محصولات', key: 'products', sortable: false },
                { title: 'مرحله', key: 'marketing_stage.name' },
                { title: 'امتیاز', key: 'score' },
                { title: 'وضعیت', key: 'status' },
                { title: 'کمپین', key: 'campaign.name' },
                { title: 'عملیات', key: 'actions', sortable: false },
              ]"
              density="compact"
              hide-default-footer
            >
              <template #item.products="{ item }">
                <CrmProductChips :products="item.products ?? []" />
              </template>
              <template #item.status="{ item }">
                {{ leadStatusLabel(item.status) }}
              </template>
              <template #item.actions="{ item }">
                <IconBtn
                  v-if="item.status !== 'converted'"
                  @click="openLeadProducts(item)"
                >
                  <VIcon icon="tabler-package" />
                </IconBtn>
                <IconBtn
                  v-if="item.status !== 'converted' && item.is_ready_for_sales"
                  @click="openConvert(item)"
                >
                  <VIcon icon="tabler-arrow-forward" />
                </IconBtn>
                <IconBtn
                  v-if="item.status !== 'converted'"
                  @click="showHandoff(openLeadHandoff(item))"
                >
                  <VIcon icon="tabler-user-share" />
                </IconBtn>
              </template>
            </VDataTable>
          </VWindowItem>

          <VWindowItem value="sales">
            <VDataTable
              :items="profile.deals"
              :headers="[
                { title: 'عنوان', key: 'title' },
                { title: 'محصولات', key: 'products', sortable: false },
                { title: 'مرحله', key: 'stage.name' },
                { title: 'مبلغ', key: 'amount' },
                { title: 'مسئول', key: 'assignee.name' },
                { title: 'عملیات', key: 'actions', sortable: false },
              ]"
              density="compact"
              hide-default-footer
            >
              <template #item.products="{ item }">
                <CrmProductChips :products="item.products ?? []" />
              </template>
              <template #item.amount="{ item }">
                {{ Number(item.amount).toLocaleString('fa-IR') }} {{ item.currency }}
              </template>
              <template #item.actions="{ item }">
                <IconBtn @click="openDealProducts(item)">
                  <VIcon icon="tabler-package" />
                </IconBtn>
                <IconBtn @click="goToDeals(item.id)">
                  <VIcon icon="tabler-layout-kanban" />
                </IconBtn>
                <IconBtn @click="showHandoff(openDealHandoff(item))">
                  <VIcon icon="tabler-user-share" />
                </IconBtn>
                <IconBtn @click="showHandoff(openDealHandoff(item), 'finance')">
                  <VIcon icon="tabler-building-bank" />
                </IconBtn>
              </template>
            </VDataTable>
          </VWindowItem>

          <VWindowItem value="activities">
            <VList v-if="profile.activities.length">
              <VListItem
                v-for="act in profile.activities"
                :key="act.id"
                :title="typeLabel(act.type) + ': ' + (act.subject || 'بدون عنوان')"
                :subtitle="formatDt(act.scheduled_at || act.happened_at)"
              />
            </VList>
            <p
              v-else
              class="text-medium-emphasis"
            >
              فعالیتی ثبت نشده است.
            </p>
          </VWindowItem>

          <VWindowItem value="tasks">
            <VList v-if="profile.tasks.length">
              <VListItem
                v-for="task in profile.tasks"
                :key="task.id"
                :title="task.title"
                :subtitle="[
                  taskStatusLabel(task.status),
                  task.assignee?.name ?? 'بدون مسئول',
                  task.due_at ? formatDt(task.due_at) : null,
                  task.status === 'completed' && formatTimeSpent(task) ? formatTimeSpent(task) : null,
                  task.status === 'completed' && formatWorkRange(task) ? formatWorkRange(task) : null,
                ].filter(Boolean).join(' — ')"
              />
            </VList>
            <p
              v-else
              class="text-medium-emphasis"
            >
              تسکی ثبت نشده است.
            </p>
          </VWindowItem>

          <VWindowItem value="timeline">
            <VTimeline
              v-if="profile.timeline.length"
              side="end"
              density="compact"
              truncate-line="both"
            >
              <VTimelineItem
                v-for="(event, idx) in profile.timeline"
                :key="idx"
                :dot-color="event.color"
                size="small"
              >
                <div class="d-flex align-center gap-2 mb-1">
                  <VIcon
                    :icon="event.icon"
                    size="18"
                  />
                  <span class="font-weight-medium">{{ event.title }}</span>
                </div>
                <p class="text-body-2 mb-0">
                  {{ event.subtitle }}
                </p>
                <p class="text-caption text-disabled">
                  {{ formatDt(event.at) }}
                </p>
              </VTimelineItem>
            </VTimeline>
            <p
              v-else
              class="text-medium-emphasis"
            >
              رویدادی در تایم‌لاین نیست.
            </p>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </template>

  <VDialog
    v-model="editDialog"
    max-width="560"
  >
    <VCard title="ویرایش مخاطب">
      <VCardText>
        <AppTextField
          v-model="editForm.name"
          label="نام"
          class="mb-3"
        />
        <AppTextField
          v-model="editForm.email"
          label="ایمیل"
          class="mb-3"
        />
        <AppTextField
          v-model="editForm.phone"
          label="تلفن"
          class="mb-3"
        />
        <AppTextField
          v-model="editForm.company"
          label="شرکت"
          class="mb-3"
        />
        <AppTextField
          v-model="editForm.job_title"
          label="سمت"
          class="mb-3"
        />
        <AppTextField
          v-model="editForm.city"
          label="شهر"
          class="mb-3"
        />
        <AppTextarea
          v-model="editForm.notes"
          label="یادداشت"
          rows="3"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="editDialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          @click="submitEdit"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="activityDialog"
    max-width="480"
  >
    <VCard title="ثبت فعالیت">
      <VCardText>
        <AppSelect
          v-model="activityForm.type"
          :items="[
            { title: 'تماس', value: 'call' },
            { title: 'جلسه', value: 'meeting' },
            { title: 'یادداشت', value: 'note' },
          ]"
          label="نوع"
          class="mb-3"
        />
        <AppTextField
          v-model="activityForm.subject"
          label="موضوع"
          class="mb-3"
        />
        <AppTextarea
          v-model="activityForm.body"
          label="توضیحات"
          rows="3"
          class="mb-3"
        />
        <VSwitch
          v-model="activityForm.scheduleFuture"
          label="زمان‌بندی آینده"
          hide-details
        />
        <AppJalaliDateTimePicker
          v-if="activityForm.scheduleFuture"
          v-model="activityForm.scheduled_date"
          v-model:time="activityForm.scheduled_time"
          label="زمان"
          class="mt-3"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="activityDialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="submitActivity"
        >
          ثبت
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="taskDialog"
    max-width="520"
  >
    <VCard title="تسک جدید">
      <VCardText>
        <AppTextField
          v-model="taskForm.title"
          label="عنوان *"
          class="mb-3"
        />
        <AppTextarea
          v-model="taskForm.description"
          label="توضیحات"
          rows="2"
          class="mb-3"
        />
        <AppSelect
          v-model="taskForm.priority"
          :items="[
            { title: 'کم', value: 'low' },
            { title: 'متوسط', value: 'medium' },
            { title: 'بالا', value: 'high' },
          ]"
          label="اولویت"
          class="mb-3"
        />
        <AppSelect
          v-model="taskForm.assignee_id"
          :items="teamUsers.map(u => ({ title: u.name, value: u.id }))"
          label="مسئول"
          clearable
          class="mb-3"
        />
        <AppJalaliDateTimePicker
          v-model="taskForm.due_date"
          v-model:time="taskForm.due_time"
          label="موعد"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="taskDialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :disabled="!taskForm.title"
          @click="submitTask"
        >
          ثبت
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <CrmEntityProductsDialog
    v-model="productsDialog"
    :entity-type="productsEntityType"
    :entity="productsEntity"
    @success="onProductsSaved"
  />

  <CrmConvertLeadDialog
    v-model="convertDialog"
    :lead="convertingLead"
    @success="onConvertSuccess"
  />

  <CrmHandoffDialog
    v-model="handoffDialog"
    :entity-type="handoffConfig.entityType"
    :entity="handoffConfig.entity"
    :stages="handoffConfig.stages"
    :users="teamUsers"
    :preset="handoffConfig.preset"
    @success="onHandoffSuccess"
  />

  <CrmHandoffReturnDialog
    v-model="returnDialog"
    :handoff="returningHandoff"
    :stages="salesStages"
    @success="onHandoffSuccess"
  />

  <CrmSmsSendDialog
    v-if="profile?.contact"
    v-model="smsDialog"
    :phone="profile.contact.phone"
    :contact-id="profile.contact.id"
    related-type="contact"
    :related-id="profile.contact.id"
  />
</template>
