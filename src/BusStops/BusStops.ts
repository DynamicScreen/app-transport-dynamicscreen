import {IAssetDownload, IAssetsStorageAbility, IPublicSlide, ISlideContext, SlideModule, VueInstance} from "dynamicscreen-sdk-js";

export default class BusStopsSlideModule extends SlideModule {
  async onReady() {
    return true;
  };

  setup(props: Record<string, any>, vue: VueInstance, context: ISlideContext) {
    const {h, reactive, ref, computed} = vue;

    return () =>
      h("div", {

      }, 'wip transport bus stops')
  }
}
