

export const CATEGORY_IMAGE_RULES: readonly (readonly [RegExp, string])[] = [
  [/drain|sewer|canal|clog/i, 'drainage'],
  [/flood/i, 'flood'],
  [/garbage|waste|trash|dump|litter/i, 'garbage'],
  [/road|pothole|pavement/i, 'pothole'],
  [/light|lamp|electric/i, 'streetlight'],
  [/tree|vegetation|grass|branch|plant/i, 'tree'],
  [/water|sanit|pipe|leak/i, 'water'],
  [/park|public space|bench|playground/i, 'park'],
  [/safety|crosswalk|pedestrian|traffic/i, 'crosswalk'],
];
