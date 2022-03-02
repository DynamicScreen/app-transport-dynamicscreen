import {IAssetDownload, IAssetsStorageAbility, IPublicSlide, ISlideContext, SlideModule, VueInstance} from "dynamicscreen-sdk-js";
import Route from "../Components/Route";

export default class ScheduledStopsSlide extends SlideModule {
  async onReady() {
    return true;
  };

  setup(props: Record<string, any>, vue: VueInstance, context: ISlideContext) {
    const { h, reactive, ref, computed } = vue;

    const slide = reactive(this.context.slide) as IPublicSlide;
    const routes = ref(slide.data.routes);
    const listScroll = ref(true);
    const scrollTimer = ref<number | boolean>(false)

    const routesContainer = ref<HTMLDivElement>();
    const container = ref<HTMLDivElement>();
    const stopContainer = ref<HTMLDivElement>();

    const getRoutesHeight = () => {
      if (!routesContainer.value) return 0;

      return routesContainer.value.scrollHeight;
    }

    const getRoutesContainerHeight = () => {
      if (!container.value || !stopContainer.value) return 0;

      return container.value.clientHeight - stopContainer.value.clientHeight;
    }

    const routesContainerStyle = computed(() => {
      if (!listScroll.value) {
        return { marginTop: 0 };
      }

      if (getRoutesHeight() > getRoutesContainerHeight()) {
        let delay = (slide.duration) / 4;
        let heightToScroll = getRoutesHeight() - getRoutesContainerHeight();

        return {
          marginTop: - heightToScroll + 'px',
          transitionProperty: 'margin-top',
          transitionDuration: delay * 2 + 's',
          transitionDelay: delay / 2 + 's',
          transitionTimingFunction: 'ease-in-out',
        };
      } else {
        return { marginTop: 0 };
      }
    })

    const stopScrolling = () => {
      if (scrollTimer.value !== false) {
        clearInterval(scrollTimer.value as number);
        scrollTimer.value = false;
      }
    };

    this.context.onReplay(async () => {
      listScroll.value = false;
      setTimeout(function() {
        return () => {
          listScroll.value = true;
        }
      }(), 500);
    });

    this.context.onPlay(async () => {
      listScroll.value = false;
      setTimeout(function() {
        return () => {
          listScroll.value = true;
        }
      }(), 500);
    });

    this.context.onEnded(async () => {
      listScroll.value = false;
      stopScrolling();
    });

    return () =>
      h("div", {
        class: "bg-red",
        ref: container
      }, [
        h("div", {
          class: "text-5xl px-12 pt-12 py-12 portrait:m-6 relative z-40",
          style: {
            background: "linear-gradient(to bottom, rgb(255 255 255) 80%, rgb(255 255 255 / 0%) 100%)"
          },
          ref: stopContainer
        }, [
          h("label", {}, [
            h("i", { class: [`fas ${slide.data.stop.icon} fa-lg`] }),
            h("span", {
              class: ["px-6 font-bold"],
            }, slide.data.stop.name)
          ])
        ]),
        h("div", {
          class: 'z-10',
          ref: routesContainer,
          style: routesContainerStyle.value
          },
          routes.value.map((route, index) => {
            return h(Route, {
              class: [(index % 2) ? '' : 'bg-gray-50', 'mx-12 portrait:m-6'],
              route: route,
              t: this.t});
          })
        )
     ])
  }
}
