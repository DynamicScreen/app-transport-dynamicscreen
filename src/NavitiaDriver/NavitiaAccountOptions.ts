import {
  ISlideContext, ISlideOptionsContext,
  SlideOptionsModule, VueInstance,
} from "dynamicscreen-sdk-js";

export default class NavitiaAccountOptions extends SlideOptionsModule {
  async onReady() {
    return true;
  };

  setup(props: Record<string, any>, vue: VueInstance, context: ISlideOptionsContext) {
    //@ts-ignore
    const { h, ref, reactive, watch, toRef, computed } = vue;
    const { Field, TextInput } = this.context.components;

    return () =>
      h("div", {}, [
        h(Field, { class: 'flex-1', label: this.t("modules.navitia_driver.options.api_key") }, [
          h(TextInput, { min: 0, max: 150, default: 1, ...this.context.update.option("key") })
        ]),
      ])
  }
}
