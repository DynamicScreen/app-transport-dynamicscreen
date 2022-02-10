import {
  ISlideOptionsContext,
  SlideOptionsModule,
  VueInstance,
} from "dynamicscreen-sdk-js"
import debounce from "debounce";

export default class BusStopsOptionsModule extends SlideOptionsModule {
  async onReady() {
    return true;
  };

  setup(props: Record<string, any>, vue: VueInstance, context: ISlideOptionsContext) {
    //@ts-ignore
    const { h, ref, reactive, watch, toRefs, toRef } = vue;
    //@ts-ignore
    const { Field, FieldsRow, Select, NumberInput, SegmentedRadio, ListPicker } = this.context.components
    const update = this.context.update;

    // const type = ref(props.slide.data.type);
    // let type, network_id, stop_area_id = ref<any>(null);
    let type = toRef(props.modelValue, 'type') || ref<string>('');
    let network_id = toRef(props.modelValue, 'network_id') || ref<number>(0);
    let stop_area_id = toRef(props.modelValue, 'stop_area_id') || ref<number>(0);
    let stops = toRef(props.modelValue, 'stops') || ref({});
    // { type, network_id, stop_area_id } = toRefs(props.modelValue);
    // watch(() => type.value, (type) => {
    //   if (searchTerm.length < 3) return []
    //
    //   searchTwitterUser(searchTerm)
    // })

    const networks: any = reactive({})
    const areNetworksLoaded = ref(false)
    this.context.getAccountData?.("navitia-driver", "networks")
      .value?.then((data: any) => {
      console.log('networks ', data)
      networks.value = Object.keys(data).map((key) => {
        return {
          'group': key,
          'options': Object.keys(data[key]).map((k) => {
            return {'id': k, 'name': data[key][k] };
          })
        };
      });
      areNetworksLoaded.value = true;
      console.log('account data successfully fetched', networks)
    }).catch((err) => {
      console.log('error while fetching account data: ', err)
      areNetworksLoaded.value = false;
    });




    const stopAreas: any = reactive({})
    const areStopAreasLoaded = ref(false)
    watch(() => network_id.value, debounce((val) => {
      const networkData = val.split(';');
      const region = networkData[0];
      const network = networkData[1];
      console.log(networkData, region, network)

      this.context.getAccountData?.("navitia-driver", "stop_areas", {
        extra: {
          region,
          network,
        }
      })
        .value?.then((data: any) => {
        console.log('stop_areas ', data)
        stopAreas.value = Object.keys(data).map((key) => {
          return {'id': data[key]['id'], 'name': data[key]['label'], icon: 'fas '+data[key]['icon'] };
        });
        areStopAreasLoaded.value = true;
        console.log('account data successfully fetched', stopAreas)
      }).catch((err) => {
        console.log('error while fetching account data: ', err)
        areStopAreasLoaded.value = false;
      })
    }, 300));





    const stopList: any = reactive({})
    const areStopsLoaded = ref(false)
    watch(() => stop_area_id.value, debounce((val) => {
      const networkData = network_id.value.split(';');
      const region = networkData[0];
      const network = networkData[1];

      this.context.getAccountData?.("navitia-driver", "stops", {
        extra: {
          region,
          network,
          stop_area: val,
        }
      })
        .value?.then((data: any) => {
        console.log('stops ', data)
        stopList.value = Object.keys(data).map((key) => {
          const color = '#'+ data[key]['line']['color'];
          const icon = 'fas '+ data[key]['line']['icon'];
          const name = `${data[key]['line']['name']} - ${data[key]['label']}`;
          return { id: key, name, color, icon };
        });
        areStopsLoaded.value = true;
        console.log('account data successfully fetched', stopList)
      }).catch((err) => {
        console.log('error while fetching account data: ', err)
        areStopsLoaded.value = false;
      })
    }, 300));


    return () => [
        // h("div", {}, [
          h(SegmentedRadio, {
            label: this.t("modules.scheduled_stops.options.type.label"),
            ...update.option("type"),
            default: "auto",
            options: [
              {
                value: "auto",
                icon: "fa fa-fw fa-map-marker",
                label: this.t("modules.scheduled_stops.options.types.auto.label")
              },
              {
                value: "fixed",
                icon: "fa fa-fw fa-list",
                label: this.t("modules.scheduled_stops.options.types.fixed.label")
              },
            ]
          }),
        // ]),


      h("div", { class: "space-y-4" }, [
          h(Field, { class: 'flex-1', label: this.t("modules.scheduled_stops.options.nb_schedules.label") }, [
            h(NumberInput, { min: 1, max: 100, default: 3, ...update.option("nb_schedules") })
          ]),
          type.value === "auto" && [
            // h("div", { class: "text-gray-400 text-xs" }, this.t("designer.value_at_creation")),
            h(FieldsRow, {}, [
              h(Field, { class: 'flex-1', label: this.t("modules.scheduled_stops.options.distance.label") }, [
                h(NumberInput, { min: 1, max: 10000, default: 500, ...update.option("distance") })
              ]),
              h(Field, { class: 'flex-1', label: this.t("modules.scheduled_stops.options.max_stops.label") }, [
                h(NumberInput, { min: 1, max: 10, default: 5, ...update.option("max_stops") })
              ])
            ]),
          ],

          type.value === "fixed" && [
            // h("div", { class: "text-gray-400 text-xs" }, this.t("designer.value_with_data_sources")),

            areNetworksLoaded.value && h(Field, { label: this.t('modules.scheduled_stops.options.network.label') }, [
              h(Select, {
                grouped: true,
                options: networks.value,
                placeholder: this.t('modules.scheduled_stops.options.network.placeholder'),
                ...update.option('network_id')
              }),
            ]),
            areStopAreasLoaded.value && h(Field, { label: this.t('modules.scheduled_stops.options.stop_area.label') }, [
              h(Select, {
                options: stopAreas.value,
                placeholder: this.t('modules.scheduled_stops.options.stop_area.placeholder'),
                ...update.option('stop_area_id')
              }),
            ]),
            areStopsLoaded.value && h(Field, { label: this.t('modules.scheduled_stops.options.stops.label') }, [
              h(ListPicker, {
                items: stopList.value,
                addText: this.t('modules.scheduled_stops.options.stops.add_stop'),
                placeholder: this.t('modules.scheduled_stops.options.stops.placeholder'),
                ...update.option('stops'),
              }),
            ]),
          ],
        ]
      )]
  }
}
