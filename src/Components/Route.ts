import {defineComponent, h, toRefs, computed, toRef, Ref} from "vue"

import moment from "moment"

export default defineComponent({
  props: {
    route: {type: Object, required: true},
    t: {type: Function, required: true}
  },
  setup(props) {
    const {route, terminuses, schedules} = toRefs(props.route)
    const nb_schedules: Ref<number> = toRef(props.route, 'nb_schedules')

    const time = (datetime) => moment(datetime).format('H:mm')
    const mn = (datetime) => moment(datetime).diff(moment(), 'minutes')
    const isIn45Mins = (datetime) => moment(datetime).diff(moment(), 'minutes') <= 45;
    const isNearby = (datetime) => moment(datetime).diff(moment(), 'minutes') < 2;
    // const schedules = computed<any[]>(() => terminuses.value[0]);
    const hasMultipleTerminus = computed(() => terminuses.value.length > 1);

    const renderSchedules = (terminus) => {
      let scheduleList = [];
      const schedule_text = (schedule) => isNearby(schedule.date_time) ? props.t('modules.scheduled_stops.slide.nearby') : mn(schedule.date_time) +" mn"

      for (let i = 0; i < nb_schedules.value; i++) {
        if (i > 5) return;

        scheduleList.push(
          // @ts-ignore
          h("div", {
            class: ["w-28 text-center text-gray-700", i === 0 ? "font-semibold" : "font-medium"]
          }, terminus[i] ? schedule_text(terminus[i]) : '-')
        );
      }

      return scheduleList;
    }

    return () =>
      h("div", {
        class: ["flex text-4xl items-center pl-4 px-4 py-7 rounded-xl", hasMultipleTerminus.value ? "pb-5" : ""],
      }, [
        h('i', {
          class: 'symbol mode-' + route.value.mode + ' shape-' + route.value.shape + ' color-' + route.value.color_type
        }),
        h("div", {
          class: ["text-center w-40 overflow-hidden pr-6 text-7xl font-medium", hasMultipleTerminus.value ? "m-auto mt-0" : '' ]
        }, [
          h("div", {
            class: ['text-ellipsis', route.value.code.length >= 4 ? 'text-4xl' : ''],
            style: { color: route.value.color }
          }, route.value.code),
        ]),
        !hasMultipleTerminus.value &&
        h("div", { class: "w-6/12" }, [
          h("div", {
            class: "flex font-light py-1 text-3xl portrait:text-2xl",
            style: { color: route.value.color }
          }, route.value.name),
          h("span", {
            class: "flex font-medium text-gray-700 py-1 portrait:text-3xl"
          }, route.value.terminus)
          ]
        ),
        !hasMultipleTerminus.value && h("div", { class: "flex w-6/12 justify-evenly text-gray-600 font-medium" },
          schedules.value.length > 0
          && isIn45Mins(schedules.value[0].date_time)
          && renderSchedules(schedules.value) || [
            h("div", {}, [
              schedules.value.length > 0
                ? props.t('modules.scheduled_stops.slide.next_schedule_at') + time(schedules.value[0].date_time)
                : h("div", {}, props.t('modules.scheduled_stops.slide.no_schedules'))
            ])
          ]
        ),
        hasMultipleTerminus.value && h("div", { class: "w-full" }, [
          h("div", {
            class: "truncate font-light py-1 text-3xl portrait:text-2xl",
            style: { color: route.value.color }
          }, [
            h("span", { class: "text-3xl font-normal portrait:text-2xl" }, route.value.name),
            h("span", { class: "m-auto text-lg portrait:text-base ml-2"}, `direction ${route.value.terminus}`)
          ]),
          h("div", { class:"" }, terminuses.value.map((terminus) => {
            return h("div", { class: "flex mt-1" }, [
              h("span", {
                class: "flex w-6/12 font-medium text-gray-700 py-1 portrait:text-3xl"
              }, terminus[0]?.destination),
              h("div", { class: "flex w-6/12 justify-evenly text-gray-600 font-medium" },
                terminus.length > 0
                && isIn45Mins(terminus[0].date_time)
                && renderSchedules(terminus)
                || [
                  h("div", {}, terminus.length > 0
                    ? props.t('modules.scheduled_stops.slide.next_schedule_at') + time(terminus[0].date_time)
                    : h("div", {}, props.t('modules.scheduled_stops.slide.no_schedules')))
                ]
              )
            ])
          }))
        ]),

      ])
  }
})